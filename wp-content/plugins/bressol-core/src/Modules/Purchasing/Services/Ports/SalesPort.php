<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface SalesPort
{
    public function is_available(): bool;

    /** @return array<string, int> */
    public function get_sales_history(int $daysBack): array;
}
