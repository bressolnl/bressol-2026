<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface InventoryReadPort
{
    /** @param int[] $productIds
     *  @return array<int, array<string, mixed>>
     */
    public function get_stock_snapshot(array $productIds, string $warehouseCode): array;
}
