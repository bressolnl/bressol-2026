<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\Adapters;

use Bressol\Modules\Purchasing\Services\Ports\StockWritePort;

if (!defined('ABSPATH')) {
    exit;
}

final class WooStockAdapter implements StockWritePort
{
    /** @param array<string, mixed> $context */
    public function increase_stock(int $productId, int $qty, array $context): void
    {
        if ($productId <= 0 || $qty <= 0) {
            return;
        }

        if (!function_exists('wc_update_product_stock')) {
            return;
        }

        wc_update_product_stock($productId, $qty, 'increase');
    }
}
