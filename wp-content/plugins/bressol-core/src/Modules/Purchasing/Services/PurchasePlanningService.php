<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Services\Ports\ForecastingPort;
use Bressol\Modules\Purchasing\Services\Ports\InventorySnapshotPort;
use Bressol\Modules\Purchasing\Services\Ports\EventsPort;
use Bressol\Modules\Purchasing\Services\Ports\SalesPort;

if (!defined('ABSPATH')) {
    exit;
}

final class PurchasePlanningService
{
    private Settings $settings;
    private PlanningStore $store;
    private InventorySnapshotPort $inventoryPort;
    private ForecastingPort $forecastingPort;
    private EventsPort $eventsPort;
    private SalesPort $salesPort;
    private AuditLogger $auditLogger;

    public function __construct(
        Settings $settings,
        PlanningStore $store,
        InventorySnapshotPort $inventoryPort,
        ForecastingPort $forecastingPort,
        EventsPort $eventsPort,
        SalesPort $salesPort,
        AuditLogger $auditLogger
    ) {
        $this->settings = $settings;
        $this->store = $store;
        $this->inventoryPort = $inventoryPort;
        $this->forecastingPort = $forecastingPort;
        $this->eventsPort = $eventsPort;
        $this->salesPort = $salesPort;
        $this->auditLogger = $auditLogger;
    }

    public function is_due(?\DateTimeImmutable $nowUtc = null): bool
    {
        $nextShipment = $this->parse_utc($this->settings->get_purchasing_planning_next_shipment_date_utc());
        if (!$nextShipment) {
            return false;
        }

        $nowUtc = $nowUtc ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $leadDays = $this->settings->get_purchasing_planning_reminder_lead_days();
        $leadDate = $nextShipment->modify('-' . $leadDays . ' days');

        return $nowUtc >= $leadDate;
    }

    /** @return array<string, mixed> */
    public function run(bool $dryRun = false): array
    {
        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cycleDays = $this->settings->get_purchasing_planning_cycle_days();
        $leadDays = $this->settings->get_purchasing_planning_reminder_lead_days();
        $nextShipment = $this->parse_utc($this->settings->get_purchasing_planning_next_shipment_date_utc());

        $windowStart = $nowUtc;
        $windowEnd = $nextShipment && $nextShipment > $nowUtc
            ? $nextShipment
            : $nowUtc->modify('+' . $cycleDays . ' days');

        $windowStartUtc = $windowStart->format('Y-m-d H:i:s');
        $windowEndUtc = $windowEnd->format('Y-m-d H:i:s');

        $payload = [
            'run_id' => uniqid('plan_', true),
            'generated_at_utc' => $nowUtc->format('Y-m-d H:i:s'),
            'window_start_utc' => $windowStartUtc,
            'window_end_utc' => $windowEndUtc,
            'next_shipment_date_utc' => $nextShipment ? $nextShipment->format('Y-m-d H:i:s') : '',
            'inputs_available' => [
                'inventory' => false,
                'forecasting' => false,
                'events' => false,
                'sales' => false,
            ],
            'suggestions' => [],
            'notes' => [],
        ];

        if (!$this->settings->is_purchase_planning_enabled()) {
            $payload['notes'][] = 'Planning disabled.';
            return $payload;
        }

        if (!$nextShipment) {
            $payload['notes'][] = 'Missing next_shipment_date_utc.';
        }

        $inventory = $this->normalize_map($this->inventoryPort->get_stock_snapshot());
        $forecast = $this->normalize_map($this->forecastingPort->get_forecast($windowStartUtc, $windowEndUtc));
        $events = $this->eventsPort->get_events($windowStartUtc, $windowEndUtc);
        $sales = $this->normalize_map($this->salesPort->get_sales_history($cycleDays));

        $payload['inputs_available'] = [
            'inventory' => $this->inventoryPort->is_available() && $inventory !== [],
            'forecasting' => $this->forecastingPort->is_available() && $forecast !== [],
            'events' => $this->eventsPort->is_available() && $events !== [],
            'sales' => $this->salesPort->is_available() && $sales !== [],
        ];

        $demand = $forecast !== [] ? $forecast : $sales;
        $rationale = $forecast !== [] ? 'forecast' : ($sales !== [] ? 'sales_proxy' : 'no_data');

        if ($demand === []) {
            $payload['notes'][] = 'No demand data available.';
        } else {
            $payload['suggestions'] = $this->build_suggestions($demand, $inventory, $rationale);
        }

        if (!$dryRun) {
            $this->store->save_run($payload);
        }

        $this->auditLogger->log('planning_run', [
            'result' => $dryRun ? 'dry_run' : 'saved',
            'window_weeks' => (int) ceil($cycleDays / 7),
            'reminder_weeks_before' => (int) ceil($leadDays / 7),
            'suggestion_count' => count($payload['suggestions']),
            'inputs_available' => $this->format_inputs_available($payload['inputs_available']),
        ]);

        return $payload;
    }

    /** @param array<string, int> $demand
     *  @param array<string, int> $stock
     *  @return array<int, array<string, mixed>>
     */
    private function build_suggestions(array $demand, array $stock, string $rationale): array
    {
        $suggestions = [];
        foreach ($demand as $key => $demandQty) {
            $stockQty = $stock[$key] ?? 0;
            $suggested = max(0, (int) $demandQty - (int) $stockQty);
            if ($suggested <= 0) {
                continue;
            }

            $item = [
                'key' => $key,
                'stock_qty' => (int) $stockQty,
                'demand_qty' => (int) $demandQty,
                'suggested_buy_qty' => (int) $suggested,
                'rationale' => $rationale,
            ];
            $normalized = $this->expand_key($key);
            if ($normalized['product_id'] !== null) {
                $item['product_id'] = $normalized['product_id'];
            }
            if ($normalized['sku'] !== null) {
                $item['sku'] = $normalized['sku'];
            }

            $suggestions[] = $item;
        }

        return $suggestions;
    }

    /** @param array<string, mixed> $input
     *  @return array<string, int>
     */
    private function normalize_map(array $input): array
    {
        $map = [];
        foreach ($input as $key => $value) {
            $normalizedKey = $this->normalize_key($key);
            if ($normalizedKey === '') {
                continue;
            }
            $map[$normalizedKey] = is_numeric($value) ? (int) $value : 0;
        }

        return $map;
    }

    private function normalize_key($key): string
    {
        if (is_int($key) || (is_string($key) && ctype_digit($key))) {
            return 'product_id:' . (int) $key;
        }

        $raw = is_string($key) ? trim($key) : '';
        if ($raw === '') {
            return '';
        }

        if (strpos($raw, 'product_id:') === 0 || strpos($raw, 'sku:') === 0) {
            return $raw;
        }

        return 'sku:' . $raw;
    }

    /** @param array<string, bool> $inputs */
    private function format_inputs_available(array $inputs): string
    {
        $parts = [];
        foreach ($inputs as $key => $value) {
            $parts[] = $key . '=' . ($value ? '1' : '0');
        }

        return implode(',', $parts);
    }

    /** @return array{product_id:?int,sku:?string} */
    private function expand_key(string $key): array
    {
        if (strpos($key, 'product_id:') === 0) {
            return [
                'product_id' => (int) substr($key, strlen('product_id:')),
                'sku' => null,
            ];
        }

        if (strpos($key, 'sku:') === 0) {
            return [
                'product_id' => null,
                'sku' => substr($key, strlen('sku:')),
            ];
        }

        return [
            'product_id' => null,
            'sku' => null,
        ];
    }

    private function parse_utc(string $input): ?\DateTimeImmutable
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $input, new \DateTimeZone('UTC'));
        if (!$date) {
            return null;
        }

        return $date;
    }
}
