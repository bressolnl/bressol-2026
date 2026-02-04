<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services;

use Bressol\Modules\Forecasting\Repositories\ForecastSnapshotRepository;
use Bressol\Modules\MarketsEvents\Services\OrderEventMetaService;

if (!defined('ABSPATH')) {
    exit;
}

final class PosSalesSnapshotBuilder
{
    private const POS_CHANNEL_META = '_bressol_pos_channel';

    private ForecastSnapshotRepository $repository;
    private PackExploder $packExploder;

    public function __construct(
        ?ForecastSnapshotRepository $repository = null,
        ?PackExploder $packExploder = null
    ) {
        $this->repository = $repository ?? new ForecastSnapshotRepository();
        $this->packExploder = $packExploder ?? new PackExploder();
    }

    /** @return array<string, mixed> */
    public function build_for_event(int $eventId): array
    {
        $warnings = [];
        $eventMetaKey = $this->resolve_event_meta_key();
        if ($eventMetaKey === null) {
            return [
                'ok' => false,
                'error' => 'ORDER_META_KEY_NOT_FOUND: Meta key de evento no detectada.',
                'warnings' => ['ORDER_META_KEY_NOT_FOUND: Meta key de evento no detectada.'],
            ];
        }

        $orders = $this->find_pos_orders_for_event($eventId, $eventMetaKey);
        $ordersCount = count($orders);
        if ($ordersCount === 0) {
            $snapshotId = $this->persist_snapshot($eventId, [], 0, 0);
            return [
                'ok' => true,
                'snapshot_id' => $snapshotId,
                'orders_count' => 0,
                'refunds_count' => 0,
                'warnings' => $warnings,
                'lines_count' => 0,
            ];
        }

        $totals = [];
        $refundsCount = 0;

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $orderLines = $this->extract_order_lines($order, $warnings);
            $refundLines = $this->extract_refund_lines($order, $warnings, $refundsCount);

            foreach ($orderLines as $line) {
                $pid = (int) ($line['product_id'] ?? 0);
                $qty = (int) ($line['qty_units'] ?? 0);
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $totals[$pid] = ($totals[$pid] ?? 0) + $qty;
            }
            foreach ($refundLines as $line) {
                $pid = (int) ($line['product_id'] ?? 0);
                $qty = (int) ($line['qty_units'] ?? 0);
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $totals[$pid] = ($totals[$pid] ?? 0) - $qty;
            }
        }

        $lines = [];
        foreach ($totals as $productId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $productId,
                'qty_units' => $qty,
            ];
        }

        if ($refundsCount > 0) {
            $warnings[] = 'REFUNDS_APPLIED: Devoluciones aplicadas al neto.';
        }

        $snapshotId = $this->persist_snapshot($eventId, $lines, $ordersCount, $refundsCount);

        return [
            'ok' => true,
            'snapshot_id' => $snapshotId,
            'orders_count' => $ordersCount,
            'refunds_count' => $refundsCount,
            'warnings' => array_values(array_unique($warnings)),
            'lines_count' => count($lines),
        ];
    }

    /** @return \WC_Order[] */
    private function find_pos_orders_for_event(int $eventId, string $eventMetaKey): array
    {
        if ($eventId <= 0 || !function_exists('wc_get_orders')) {
            return [];
        }

        $orders = [];
        $page = 1;
        $limit = 200;

        do {
            $query = [
                'status' => 'completed',
                'return' => 'ids',
                'limit' => $limit,
                'paged' => $page,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => self::POS_CHANNEL_META,
                        'value' => 'pos',
                        'compare' => '=',
                    ],
                    [
                        'key' => $eventMetaKey,
                        'value' => (string) $eventId,
                        'compare' => '=',
                    ],
                ],
            ];

            $ids = wc_get_orders($query);
            if ($ids === []) {
                break;
            }

            foreach ($ids as $orderId) {
                $order = wc_get_order($orderId);
                if ($order instanceof \WC_Order) {
                    $orders[] = $order;
                }
            }

            $page++;
        } while (count($ids) === $limit && $page <= 20);

        return $orders;
    }

    /** @param string[] $warnings
     *  @return array<int, array{product_id:int,qty_units:int}>
     */
    private function extract_order_lines(\WC_Order $order, array &$warnings): array
    {
        $lines = [];

        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $qty = (int) $item->get_quantity();
            if ($qty <= 0) {
                continue;
            }

            $explode = $this->packExploder->explode_order_item($item, $qty);
            $lines = array_merge($lines, $explode['lines']);
            if ($explode['warnings'] !== []) {
                foreach ($explode['warnings'] as $warning) {
                    $warnings[] = $warning;
                }
            }
        }

        return $lines;
    }

    /** @param string[] $warnings
     *  @return array<int, array{product_id:int,qty_units:int}>
     */
    private function extract_refund_lines(\WC_Order $order, array &$warnings, int &$refundsCount): array
    {
        $lines = [];
        $refunds = $order->get_refunds();
        if ($refunds === []) {
            return [];
        }

        foreach ($refunds as $refund) {
            if (!$refund instanceof \WC_Order_Refund) {
                continue;
            }
            $refundsCount++;
            foreach ($refund->get_items() as $item) {
                if (!$item instanceof \WC_Order_Item_Product) {
                    continue;
                }
                $qty = (int) abs($item->get_quantity());
                if ($qty <= 0) {
                    continue;
                }
                $explode = $this->packExploder->explode_order_item($item, $qty);
                $lines = array_merge($lines, $explode['lines']);
                if ($explode['warnings'] !== []) {
                    foreach ($explode['warnings'] as $warning) {
                        $warnings[] = $warning;
                    }
                }
            }
        }

        return $lines;
    }

    /** @param array<int, array{product_id:int,qty_units:int}> $lines */
    private function persist_snapshot(int $eventId, array $lines, int $ordersCount, int $refundsCount): int
    {
        $note = 'orders_count=' . $ordersCount . ';refunds_count=' . $refundsCount
            . ';generated_at_utc=' . gmdate('Y-m-d H:i:s');

        $snapshotRow = $this->repository->get_latest_snapshot_for_event_and_source($eventId, 'pos');
        $snapshotId = $snapshotRow ? (int) ($snapshotRow['id'] ?? 0) : 0;
        if ($snapshotId <= 0) {
            $snapshotId = $this->repository->create_snapshot(
                $eventId,
                'pos',
                gmdate('Y-m-d H:i:s'),
                get_current_user_id(),
                $note
            );
        } else {
            $this->repository->update_snapshot_by_id($snapshotId, gmdate('Y-m-d H:i:s'), get_current_user_id(), $note);
        }

        if ($snapshotId > 0) {
            $this->repository->replace_lines($snapshotId, $lines);
        }

        return $snapshotId;
    }

    private function resolve_event_meta_key(): ?string
    {
        if (class_exists(OrderEventMetaService::class)) {
            return OrderEventMetaService::META_KEY;
        }

        return null;
    }
}
