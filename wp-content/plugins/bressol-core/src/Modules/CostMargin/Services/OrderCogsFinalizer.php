<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Services;

use Bressol\Modules\CostMargin\Transport\Repositories\TransportAllocationRepository;
use Bressol\Modules\Inventory\Lots\Repositories\LotMoveRepository;
use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderCogsFinalizer
{
    private LotRepository $lotRepository;
    private LotMoveRepository $lotMoveRepository;
    private TransportAllocationRepository $transportRepository;

    public function __construct(
        ?LotRepository $lotRepository = null,
        ?LotMoveRepository $lotMoveRepository = null,
        ?TransportAllocationRepository $transportRepository = null
    ) {
        $this->lotRepository = $lotRepository ?? new LotRepository();
        $this->lotMoveRepository = $lotMoveRepository ?? new LotMoveRepository();
        $this->transportRepository = $transportRepository ?? new TransportAllocationRepository();
    }

    public function handle_order_completed(int $orderId): void
    {
        $this->finalize_order($orderId);
    }

    public function handle_order_refunded(int $orderId, int $refundId): void
    {
        $this->process_refund($orderId, $refundId);
    }

    public function finalize_order(int $orderId): void
    {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            return;
        }

        $status = (string) $order->get_meta('_bressol_cogs_status');
        if ($status === 'final') {
            return;
        }

        if ($this->has_existing_consumes($orderId)) {
            return;
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $lineAllocations = [];
            $decrements = [];
            $totalCogs = 0;

            foreach ($order->get_items() as $item) {
                if (!$item instanceof \WC_Order_Item_Product) {
                    continue;
                }

                $itemQty = (int) $item->get_quantity();
                if ($itemQty <= 0) {
                    continue;
                }

                $requirements = $this->extract_item_requirements($item, $itemQty);
                if ($requirements === []) {
                    continue;
                }

                $allocations = [];
                $lineCogs = 0;
                foreach ($requirements as $requirement) {
                    $productId = (int) $requirement['product_id'];
                    $requiredQty = (int) $requirement['qty'];
                    $isPackComponent = !empty($requirement['is_pack_component']);
                    $fefo = $this->allocate_fefo($productId, $requiredQty, $isPackComponent);
                    if ($fefo === []) {
                        throw new \RuntimeException('missing lots for product ' . $productId);
                    }
                    foreach ($fefo as $allocation) {
                        $allocations[] = [
                            'lot_id' => $allocation['lot_id'],
                            'qty' => $allocation['qty'],
                        ];
                        $decrements[] = [
                            'lot_id' => $allocation['lot_id'],
                            'qty' => $allocation['qty'],
                            'order_item_id' => (int) $item->get_id(),
                            'product_id' => $productId,
                            'is_pack_component' => $allocation['is_pack_component'],
                        ];
                        $unitCogs = (int) ($allocation['unit_cogs_cents'] ?? 0);
                        if ($unitCogs <= 0) {
                            throw new \RuntimeException('missing unit cogs for lot ' . (int) $allocation['lot_id']);
                        }
                        $transportUnit = $this->transportRepository->get_unit_transport_cents_for_lot((int) $allocation['lot_id']);
                        $lineCogs += ($unitCogs + $transportUnit) * (int) $allocation['qty'];
                    }
                }

                $lineAllocations[(int) $item->get_id()] = $allocations;
                $totalCogs += $lineCogs;
                $item->update_meta_data('_bressol_lot_allocations', wp_json_encode($allocations));
                $item->update_meta_data('_bressol_cogs_line_cents', $lineCogs);
            }

            foreach ($decrements as $dec) {
                $lotId = (int) $dec['lot_id'];
                $qty = (int) $dec['qty'];
                if (!$this->lotRepository->increment_qty($lotId, -$qty)) {
                    throw new \RuntimeException('insufficient lot qty');
                }

                $note = wp_json_encode([
                    'order_id' => $orderId,
                    'order_item_id' => (int) $dec['order_item_id'],
                    'product_id' => (int) $dec['product_id'],
                    'is_pack_component' => (bool) $dec['is_pack_component'],
                ]);

                $this->lotMoveRepository->add_move(
                    $lotId,
                    'consume',
                    $qty,
                    'order',
                    (string) $orderId,
                    $note ?: null
                );
            }

            $order->update_meta_data('_bressol_cogs_status', 'final');
            $order->update_meta_data('_bressol_cogs_real_cents', $totalCogs);
            $order->update_meta_data('_bressol_cogs_note', '');
            $order->save();
            $wpdb->query('COMMIT');
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            $order->update_meta_data('_bressol_cogs_status', 'pending');
            $order->update_meta_data('_bressol_cogs_note', substr($exception->getMessage(), 0, 120));
            $order->save();
            $this->log_debug('cogs_finalize_failed', $orderId);
        }
    }

    public function process_refund(int $orderId, int $refundId): void
    {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            return;
        }

        $totalRefunded = (float) $order->get_total_refunded();
        $orderTotal = (float) $order->get_total();
        if ($totalRefunded + 0.01 < $orderTotal) {
            $this->log_debug('cogs_refund_partial', $orderId);
            return;
        }

        $status = (string) $order->get_meta('_bressol_cogs_status');
        if ($status === 'refunded') {
            return;
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            foreach ($order->get_items() as $item) {
                if (!$item instanceof \WC_Order_Item_Product) {
                    continue;
                }

                $raw = $item->get_meta('_bressol_lot_allocations', true);
                $allocations = is_string($raw) ? json_decode($raw, true) : $raw;
                if (!is_array($allocations)) {
                    continue;
                }

                foreach ($allocations as $allocation) {
                    if (!is_array($allocation)) {
                        continue;
                    }
                    $lotId = isset($allocation['lot_id']) ? (int) $allocation['lot_id'] : 0;
                    $qty = isset($allocation['qty']) ? (int) $allocation['qty'] : 0;
                    if ($lotId <= 0 || $qty <= 0) {
                        continue;
                    }

                    if (!$this->lotRepository->increment_qty($lotId, $qty)) {
                        throw new \RuntimeException('failed restock');
                    }

                    $note = wp_json_encode([
                        'order_id' => $orderId,
                        'refund_id' => $refundId,
                    ]);

                    $this->lotMoveRepository->add_move(
                        $lotId,
                        'adjust',
                        $qty,
                        'order',
                        (string) $orderId,
                        $note ?: null
                    );
                }
            }

            $order->update_meta_data('_bressol_cogs_status', 'refunded');
            $order->save();

            $wpdb->query('COMMIT');
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            $this->log_debug('cogs_refund_failed', $orderId);
        }
    }

    /** @return array<int, array{lot_id:int,qty:int,unit_cogs_cents:int,is_pack_component:bool}> */
    private function allocate_fefo(int $productId, int $qty, bool $isPackComponent): array
    {
        if ($productId <= 0 || $qty <= 0) {
            return [];
        }

        $lots = $this->lotRepository->get_lots_for_product_location($productId, 'NL');
        usort($lots, static function (array $a, array $b): int {
            $expA = isset($a['expiry_date']) && $a['expiry_date'] !== '' ? (string) $a['expiry_date'] : null;
            $expB = isset($b['expiry_date']) && $b['expiry_date'] !== '' ? (string) $b['expiry_date'] : null;
            if ($expA === null && $expB !== null) {
                return 1;
            }
            if ($expA !== null && $expB === null) {
                return -1;
            }
            if ($expA !== null && $expB !== null && $expA !== $expB) {
                return strcmp($expA, $expB);
            }
            $createdA = isset($a['created_at']) ? (string) $a['created_at'] : '';
            $createdB = isset($b['created_at']) ? (string) $b['created_at'] : '';
            return strcmp($createdA, $createdB);
        });
        $today = current_time('Y-m-d');
        $remaining = $qty;
        $allocations = [];

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $available = (int) ($lot['qty_on_hand'] ?? 0);
            if ($available <= 0) {
                continue;
            }
            $expiry = (string) ($lot['expiry_date'] ?? '');
            if ($expiry !== '' && $expiry < $today) {
                continue;
            }
            $take = min($available, $remaining);
            $allocations[] = [
                'lot_id' => (int) ($lot['id'] ?? 0),
                'qty' => $take,
                'unit_cogs_cents' => (int) ($lot['unit_cogs_cents'] ?? 0),
                'is_pack_component' => $isPackComponent,
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            return [];
        }

        return $allocations;
    }

    /** @return array<int, array{product_id:int,qty:int,is_pack_component:bool}> */
    private function extract_item_requirements(\WC_Order_Item_Product $item, int $itemQty): array
    {
        $pack = $item->get_meta('_bressol_pack', true);
        if (!is_array($pack)) {
            $productId = (int) $item->get_product_id();
            return $productId > 0 ? [[
                'product_id' => $productId,
                'qty' => $itemQty,
                'is_pack_component' => false,
            ]] : [];
        }

        $selections = $pack['selections'] ?? null;
        if (!is_array($selections)) {
            $this->log_debug('pack_selection_missing', (int) $item->get_order_id());
            return [];
        }

        $requirements = [];
        foreach ($selections as $lines) {
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
                $qty = isset($line['qty']) ? (int) $line['qty'] : 0;
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }
                $requirements[] = [
                    'product_id' => $productId,
                    'qty' => $qty * $itemQty,
                    'is_pack_component' => true,
                ];
            }
        }

        return $requirements;
    }

    private function has_existing_consumes(int $orderId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_lot_moves';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE ref_type = %s AND ref_id = %s AND type = %s",
            'order',
            (string) $orderId,
            'consume'
        ));

        return $count > 0;
    }

    private function log_debug(string $code, int $orderId): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log('[bressol_cogs] ' . $code . ' order=' . $orderId);
    }
}
