<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\Adapters;

use Bressol\Modules\Purchasing\Services\Ports\SalesPort;
use Bressol\Modules\SalesAnalytics\Repositories\OrderQuery;

if (!defined('ABSPATH')) {
    exit;
}

final class SalesAdapter implements SalesPort
{
    public function is_available(): bool
    {
        return class_exists(OrderQuery::class) && function_exists('wc_get_order');
    }

    public function get_sales_history(int $daysBack): array
    {
        if (!$this->is_available()) {
            return [];
        }

        $daysBack = max(1, $daysBack);
        $from = gmdate('Y-m-d', time() - ($daysBack * 86400));
        $to = gmdate('Y-m-d');

        $query = new OrderQuery();
        $orderIds = $query->find_order_ids([
            'date_from' => $from,
            'date_to' => $to,
        ], 200, 1);

        $map = [];
        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);
            if (!$order) {
                continue;
            }

            foreach ($order->get_items() as $item) {
                if (!$item instanceof \WC_Order_Item_Product) {
                    continue;
                }
                $productId = (int) $item->get_product_id();
                $qty = (int) $item->get_quantity();
                if ($qty <= 0) {
                    continue;
                }

                if ($productId > 0) {
                    $key = 'product_id:' . $productId;
                    $map[$key] = ($map[$key] ?? 0) + $qty;
                }

                $product = $item->get_product();
                if ($product) {
                    $sku = (string) $product->get_sku();
                    if ($sku !== '') {
                        $key = 'sku:' . $sku;
                        $map[$key] = ($map[$key] ?? 0) + $qty;
                    }
                }
            }
        }

        return $map;
    }
}
