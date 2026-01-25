<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Services;

use Bressol\Modules\SalesAnalytics\Repositories\OrderQuery;
use Bressol\Modules\SalesAnalytics\Services\AuditLogger;

if (!defined('ABSPATH')) {
    exit;
}

final class EventRollupService
{
    private OrderQuery $orderQuery;
    private TaxBreakdownService $taxBreakdownService;
    private CacheService $cacheService;
    private AuditLogger $auditLogger;

    public function __construct(?CacheService $cacheService = null)
    {
        $this->orderQuery = new OrderQuery();
        $this->taxBreakdownService = new TaxBreakdownService();
        $this->cacheService = $cacheService ?? new CacheService();
        $this->auditLogger = new AuditLogger();
    }

    /** @param array<string, mixed> $filters
     *  @return array{rows:array<int, array<string, mixed>>, meta:array<string, mixed>, too_large:bool}
     */
    public function get_rollup(array $filters, int $ttlSeconds, int $maxOrders): array
    {
        $cached = $this->cacheService->get('events_rollup', $filters);
        if (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) {
            $cached['too_large'] = false;
            $cached['meta']['cache_hit'] = true;
            return $cached;
        }

        $rows = [];
        $invalidDurationEvents = [];
        $invalidDurationLimit = 10;
        $totalOrders = 0;
        $page = 1;
        $limit = 200;

        do {
            $orderIds = $this->orderQuery->find_order_ids($filters, $limit, $page);
            if ($orderIds === []) {
                break;
            }

            foreach ($orderIds as $orderId) {
                $order = wc_get_order($orderId);
                if (!$order instanceof \WC_Order) {
                    continue;
                }

                $totalOrders++;
                if ($totalOrders > $maxOrders) {
                    return [
                        'rows' => [],
                        'meta' => [
                            'orders_count' => $totalOrders,
                            'computed_at' => current_time('mysql'),
                        ],
                        'too_large' => true,
                    ];
                }

                $eventId = $this->extract_event_id($order);
                if (!isset($rows[$eventId])) {
                    $rows[$eventId] = $this->init_bucket($eventId);
                }

                $bucket = &$rows[$eventId];
                $bucket['orders_count']++;

                $totalIncl = $this->to_cents((float) $order->get_total());
                $taxTotal = $this->to_cents((float) $order->get_total_tax());
                $totalExcl = $totalIncl - $taxTotal;
                $refundsIncl = $this->to_cents((float) $order->get_total_refunded());
                $refundsTax = $this->to_cents((float) $order->get_total_tax_refunded());
                $refundsExcl = max(0, $refundsIncl - $refundsTax);
                $marketCost = $this->resolve_channel($order) === 'pos'
                    ? (int) $order->get_meta('_bressol_pos_market_cost_cents')
                    : 0;

                $bucket['total_incl_tax_cents'] += $totalIncl;
                $bucket['tax_total_cents'] += $taxTotal;
                $bucket['total_excl_tax_cents'] += $totalExcl;
                $bucket['refunds_incl_tax_cents'] += $refundsIncl;
                $bucket['refunds_excl_tax_cents'] += $refundsExcl;
                $bucket['market_cost_cents'] += $marketCost;

                $breakdown = $this->taxBreakdownService->breakdown_for_order($order);
                $this->merge_breakdown($bucket['tax_breakdown'], $breakdown);
                unset($bucket);
            }

            $page++;
        } while (true);

        $events = $this->fetch_events(array_keys($rows));
        foreach ($rows as $eventId => &$bucket) {
            $event = $events[$eventId] ?? null;
            if ($event) {
                $bucket['event_title'] = (string) ($event['title'] ?? '');
                $bucket['type'] = (string) ($event['type'] ?? '');
                $bucket['start_at'] = (string) ($event['start_at'] ?? '');
                $bucket['city'] = (string) ($event['city'] ?? '');
                $bucket['event_cost_fixed_cents'] = (int) ($event['cost_fixed_cents'] ?? 0);
                $bucket['event_cost_variable_cents'] = $this->compute_variable_cost($event, $bucket, $invalidDurationEvents, $invalidDurationLimit);
            }

            $bucket['profit_estimated_excl_tax_cents'] = $bucket['total_excl_tax_cents']
                - $bucket['refunds_excl_tax_cents']
                - $bucket['market_cost_cents']
                - $bucket['event_cost_fixed_cents']
                - $bucket['event_cost_variable_cents'];
        }
        unset($bucket);

        $payload = [
            'rows' => $rows,
            'meta' => [
                'computed_at' => current_time('mysql'),
                'orders_count' => $totalOrders,
                'cache_hit' => false,
            ],
        ];
        $this->cacheService->set('events_rollup', $filters, $payload, $ttlSeconds);

        if ($invalidDurationEvents !== []) {
            $this->auditLogger->log('sales_events_invalid_duration', [
                'export_type' => 'events',
                'event_ids' => $invalidDurationEvents,
                'result' => 'warn',
            ]);
        }

        return [
            'rows' => $rows,
            'meta' => $payload['meta'],
            'too_large' => false,
        ];
    }

    private function extract_event_id(\WC_Order $order): int
    {
        $eventIdRaw = $order->get_meta('_bressol_event_id');
        return is_numeric($eventIdRaw) ? (int) $eventIdRaw : 0;
    }

    private function resolve_channel(\WC_Order $order): string
    {
        $meta = (string) $order->get_meta('_bressol_pos_channel');
        return $meta === 'pos' ? 'pos' : 'web';
    }

    private function to_cents(float $value): int
    {
        return (int) round($value * 100);
    }

    /** @return array<string, mixed> */
    private function init_bucket(int $eventId): array
    {
        $label = $eventId > 0 ? '' : 'Sin evento';
        return [
            'event_id' => $eventId,
            'event_title' => $label,
            'type' => '',
            'start_at' => '',
            'city' => '',
            'orders_count' => 0,
            'total_incl_tax_cents' => 0,
            'tax_total_cents' => 0,
            'total_excl_tax_cents' => 0,
            'refunds_incl_tax_cents' => 0,
            'refunds_excl_tax_cents' => 0,
            'market_cost_cents' => 0,
            'event_cost_fixed_cents' => 0,
            'event_cost_variable_cents' => 0,
            'profit_estimated_excl_tax_cents' => 0,
            'tax_breakdown' => [],
        ];
    }

    /** @param array<int, int|string> $eventIds
     *  @return array<int, array<string, mixed>>
     */
    private function fetch_events(array $eventIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $eventIds), static fn(int $id) => $id > 0));
        if ($ids === []) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_events';
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $sql = "SELECT id, title, type, start_at, end_at, city, cost_fixed_cents, cost_variable_json FROM {$table} WHERE id IN ({$placeholders})";
        $prepared = $wpdb->prepare($sql, $ids);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $map[$id] = $row;
            }
        }

        return $map;
    }

    /** @param array<string, mixed> $event
     *  @param array<string, mixed> $bucket
     */
    private function compute_variable_cost(
        array $event,
        array $bucket,
        array &$invalidDurationEvents,
        int $invalidDurationLimit
    ): int
    {
        $json = isset($event['cost_variable_json']) ? (string) $event['cost_variable_json'] : '';
        if (trim($json) === '') {
            return 0;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return 0;
        }

        $total = 0;
        if (isset($decoded['per_day_cents']) && is_numeric($decoded['per_day_cents'])) {
            $duration = $this->compute_duration_days(
                isset($event['start_at']) ? (string) $event['start_at'] : '',
                isset($event['end_at']) ? (string) $event['end_at'] : ''
            );
            $days = $duration['days'];
            if (!$duration['valid']) {
                $eventId = isset($event['id']) ? (int) $event['id'] : 0;
                if ($eventId > 0 && count($invalidDurationEvents) < $invalidDurationLimit && !in_array($eventId, $invalidDurationEvents, true)) {
                    $invalidDurationEvents[] = $eventId;
                }
            }
            $total += max(0, (int) $decoded['per_day_cents']) * $days;
        }
        if (isset($decoded['percent_sales_basis_points']) && is_numeric($decoded['percent_sales_basis_points'])) {
            $bps = max(0, (int) $decoded['percent_sales_basis_points']);
            $base = max(0, (int) ($bucket['total_excl_tax_cents'] ?? 0) - (int) ($bucket['refunds_excl_tax_cents'] ?? 0));
            $total += (int) round(($base * $bps) / 10000);
        }
        if (isset($decoded['flat_cents']) && is_numeric($decoded['flat_cents'])) {
            $total += max(0, (int) $decoded['flat_cents']);
        }

        return $total;
    }

    /** @return array{days:int, valid:bool} */
    private function compute_duration_days(string $start, string $end): array
    {
        if ($start === '' || $end === '') {
            return ['days' => 1, 'valid' => false];
        }

        $tz = wp_timezone();
        $startAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start, $tz);
        $endAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end, $tz);
        if (!$startAt || !$endAt) {
            return ['days' => 1, 'valid' => false];
        }

        $diff = $endAt->getTimestamp() - $startAt->getTimestamp();
        if ($diff <= 0) {
            return ['days' => 1, 'valid' => false];
        }

        return ['days' => max(1, (int) ceil($diff / DAY_IN_SECONDS)), 'valid' => true];
    }

    /** @param array<string, array{tax_cents:int, taxable_base_cents:int}> $target
     *  @param array<string, array{tax_cents:int, taxable_base_cents:int}> $source
     */
    private function merge_breakdown(array &$target, array $source): void
    {
        foreach ($source as $rate => $data) {
            if (!isset($target[$rate])) {
                $target[$rate] = [
                    'tax_cents' => 0,
                    'taxable_base_cents' => 0,
                ];
            }

            $target[$rate]['tax_cents'] += (int) $data['tax_cents'];
            $target[$rate]['taxable_base_cents'] += (int) $data['taxable_base_cents'];
        }
    }
}
