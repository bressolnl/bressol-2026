<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface InventorySnapshotPort
{
    public function is_available(): bool;

    /** @return array<string, int> */
    public function get_stock_snapshot(): array;
}
