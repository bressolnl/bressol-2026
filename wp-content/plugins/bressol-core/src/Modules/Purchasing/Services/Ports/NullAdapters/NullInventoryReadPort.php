<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\NullAdapters;

use Bressol\Modules\Purchasing\Services\Ports\InventoryReadPort;

if (!defined('ABSPATH')) {
    exit;
}

final class NullInventoryReadPort implements InventoryReadPort
{
    public function get_stock_snapshot(array $productIds, string $warehouseCode): array
    {
        return [];
    }
}
