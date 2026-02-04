<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services;

use Bressol\Modules\Inventory\Repositories\PackDefinitionRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class PackExploder
{
    private PackDefinitionRepository $packRepository;

    public function __construct(?PackDefinitionRepository $packRepository = null)
    {
        $this->packRepository = $packRepository ?? new PackDefinitionRepository();
    }

    /** @return array{lines:array<int, array{product_id:int,qty_units:int}>,warnings:string[]} */
    public function explode_order_item(\WC_Order_Item_Product $item, int $orderItemQty): array
    {
        $warnings = [];
        $lines = [];

        $productId = (int) $item->get_product_id();
        if ($productId <= 0) {
            return ['lines' => [], 'warnings' => []];
        }

        $isPack = $this->packRepository->is_pack_product($productId);
        if (!$isPack) {
            return [
                'lines' => [
                    ['product_id' => $productId, 'qty_units' => max(0, $orderItemQty)],
                ],
                'warnings' => [],
            ];
        }

        $packMeta = $item->get_meta('_bressol_pack', true);
        if (is_array($packMeta) && isset($packMeta['selections']) && is_array($packMeta['selections'])) {
            $requirements = $this->packRepository->normalize_selection_from_cart_item([
                'bressol_pack' => $packMeta,
            ]);
            foreach ($requirements as $componentId => $qty) {
                $componentId = (int) $componentId;
                $qty = (int) $qty;
                if ($componentId <= 0 || $qty <= 0) {
                    continue;
                }
                $lines[] = [
                    'product_id' => $componentId,
                    'qty_units' => $qty * max(1, $orderItemQty),
                ];
            }
        }

        if ($lines === []) {
            $warnings[] = 'PACK_NO_COMPONENTS: Pack sin definición en el pedido.';
            $lines[] = ['product_id' => $productId, 'qty_units' => max(0, $orderItemQty)];
        }

        return [
            'lines' => $lines,
            'warnings' => $warnings,
        ];
    }
}
