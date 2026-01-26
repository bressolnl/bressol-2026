<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\Adapters;

use Bressol\Modules\Purchasing\Services\Ports\InventorySnapshotPort;

if (!defined('ABSPATH')) {
    exit;
}

final class InventoryAdapter implements InventorySnapshotPort
{
    public function is_available(): bool
    {
        return function_exists('wc_get_products');
    }

    public function get_stock_snapshot(): array
    {
        if (!$this->is_available()) {
            return [];
        }

        $products = wc_get_products([
            'limit' => 200,
            'status' => 'publish',
            'type' => ['simple', 'variation'],
        ]);

        $snapshot = [];
        foreach ($products as $product) {
            if (!$product instanceof \WC_Product) {
                continue;
            }
            if (!$product->get_manage_stock()) {
                continue;
            }

            $productId = (int) $product->get_id();
            $qty = $product->get_stock_quantity();
            if ($productId <= 0 || $qty === null) {
                continue;
            }

            $qtyInt = (int) $qty;
            $snapshot['product_id:' . $productId] = $qtyInt;

            $sku = (string) $product->get_sku();
            if ($sku !== '' && !isset($snapshot['sku:' . $sku])) {
                $snapshot['sku:' . $sku] = $qtyInt;
            }
        }

        return $snapshot;
    }
}
