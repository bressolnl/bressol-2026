<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\NullAdapters;

use Bressol\Modules\Purchasing\Services\Ports\InventorySnapshotPort;

if (!defined('ABSPATH')) {
    exit;
}

final class NullInventorySnapshotPort implements InventorySnapshotPort
{
    public function is_available(): bool
    {
        return false;
    }

    public function get_stock_snapshot(): array
    {
        return [];
    }
}
