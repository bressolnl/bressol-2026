<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\NullAdapters;

use Bressol\Modules\Purchasing\Services\Ports\SalesPort;

if (!defined('ABSPATH')) {
    exit;
}

final class NullSalesPort implements SalesPort
{
    public function is_available(): bool
    {
        return false;
    }

    public function get_sales_history(int $daysBack): array
    {
        return [];
    }
}
