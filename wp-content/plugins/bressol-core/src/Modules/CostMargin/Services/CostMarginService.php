<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Services;

use Bressol\Modules\CostMargin\Transport\Repositories\TransportAllocationRepository;
use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class CostMarginService
{
    private LotRepository $lotRepository;
    private TransportAllocationRepository $transportRepository;

    public function __construct(
        ?LotRepository $lotRepository = null,
        ?TransportAllocationRepository $transportRepository = null
    ) {
        $this->lotRepository = $lotRepository ?? new LotRepository();
        $this->transportRepository = $transportRepository ?? new TransportAllocationRepository();
    }

    public function get_unit_cost_cents(int $productId, string $location = 'NL', ?string $atDate = null): ?int
    {
        if ($productId <= 0) {
            return null;
        }

        $location = strtoupper($location);
        if (!in_array($location, ['NL', 'ES'], true)) {
            return null;
        }

        $atDate = $atDate ?? current_time('Y-m-d');

        $lots = $this->lotRepository->get_lots_for_product_location($productId, $location);
        foreach ($lots as $lot) {
            $qty = (int) ($lot['qty_on_hand'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $expiry = (string) ($lot['expiry_date'] ?? '');
            if ($location === 'NL' && $expiry !== '' && $expiry < $atDate) {
                continue;
            }

            $unitCogs = (int) ($lot['unit_cogs_cents'] ?? 0);
            if ($unitCogs <= 0) {
                return null;
            }
            $transportUnit = $this->transportRepository->get_unit_transport_cents_for_lot((int) ($lot['id'] ?? 0));

            return $unitCogs + $transportUnit;
        }

        return null;
    }

    /** @return array{cogs_cents:int,missing_cost:bool} */
    public function estimate_order_cogs_cents(int $orderId): array
    {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return ['cogs_cents' => 0, 'missing_cost' => false];
        }

        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            return ['cogs_cents' => 0, 'missing_cost' => false];
        }

        $total = 0;
        $missingCost = false;
        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $qty = (int) $item->get_quantity();
            if ($qty <= 0) {
                continue;
            }

            $allocations = $this->extract_lot_allocations($item);
            if ($allocations !== []) {
                $total += $this->sum_allocations_cost($allocations, $missingCost);
                continue;
            }

            $packRequirements = $this->extract_pack_requirements($item);
            if ($packRequirements !== []) {
                foreach ($packRequirements as $productId => $perPackQty) {
                    $needed = $perPackQty * $qty;
                    if ($needed <= 0) {
                        continue;
                    }
                    $unitCost = $this->get_unit_cost_cents((int) $productId, 'NL', current_time('Y-m-d'));
                    if ($unitCost === null) {
                        $this->log_missing_cost('pack_item_missing_unit_cost', (int) $productId);
                        $missingCost = true;
                        continue;
                    }
                    $total += $unitCost * $needed;
                }
                continue;
            }

            $productId = (int) $item->get_product_id();
            if ($productId <= 0) {
                continue;
            }
            $unitCost = $this->get_unit_cost_cents($productId, 'NL', current_time('Y-m-d'));
            if ($unitCost === null) {
                $this->log_missing_cost('item_missing_unit_cost', $productId);
                $missingCost = true;
                continue;
            }
            $total += $unitCost * $qty;
        }

        return ['cogs_cents' => $total, 'missing_cost' => $missingCost];
    }

    /** @return array<int, array{lot_id:int,qty:int}> */
    private function extract_lot_allocations(\WC_Order_Item_Product $item): array
    {
        $raw = $item->get_meta('_bressol_lot_allocations', true);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (!is_array($raw) || $raw === []) {
            return [];
        }

        $allocations = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $lotId = isset($entry['lot_id']) ? (int) $entry['lot_id'] : 0;
            $qty = isset($entry['qty']) ? (int) $entry['qty'] : 0;
            if ($lotId <= 0 || $qty <= 0) {
                continue;
            }
            $allocations[] = [
                'lot_id' => $lotId,
                'qty' => $qty,
            ];
        }

        return $allocations;
    }

    /** @param array<int, array{lot_id:int,qty:int}> $allocations */
    private function sum_allocations_cost(array $allocations, bool &$missingCost): int
    {
        $sum = 0;
        foreach ($allocations as $allocation) {
            $lot = $this->lotRepository->get_lot_by_id((int) $allocation['lot_id']);
            if (!$lot) {
                $this->log_missing_cost('missing_lot_for_allocation', (int) $allocation['lot_id']);
                $missingCost = true;
                continue;
            }
            $unitCogs = (int) ($lot['unit_cogs_cents'] ?? 0);
            $transportUnit = $this->transportRepository->get_unit_transport_cents_for_lot((int) $allocation['lot_id']);
            $sum += ($unitCogs + $transportUnit) * (int) $allocation['qty'];
        }

        return $sum;
    }

    /** @return array<int, int> product_id => qty_per_pack */
    private function extract_pack_requirements(\WC_Order_Item_Product $item): array
    {
        $pack = $item->get_meta('_bressol_pack', true);
        if (!is_array($pack)) {
            return [];
        }

        if (!array_key_exists('selections', $pack)) {
            $requirements = [];
            foreach ($pack as $productId => $qty) {
                $pid = is_numeric($productId) ? (int) $productId : 0;
                $q = is_numeric($qty) ? (int) $qty : 0;
                if ($pid <= 0 || $q < 1 || $q > 500) {
                    $this->log_missing_cost('invalid_pack_entry', 0);
                    continue;
                }
                $requirements[$pid] = ($requirements[$pid] ?? 0) + $q;
            }
            return $requirements;
        }

        $selections = $pack['selections'] ?? null;
        if (!is_array($selections)) {
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
                if ($productId <= 0 || $qty < 1 || $qty > 500) {
                    $this->log_missing_cost('invalid_pack_entry', 0);
                    continue;
                }
                $requirements[$productId] = ($requirements[$productId] ?? 0) + $qty;
            }
        }

        return $requirements;
    }

    private function log_missing_cost(string $reason, int $subjectId): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log('[bressol_cost] ' . $reason . ' subject=' . $subjectId);
    }
}
