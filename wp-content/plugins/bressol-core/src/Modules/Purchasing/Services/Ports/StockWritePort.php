<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface StockWritePort
{
    /** @param array<string, mixed> $context */
    public function increase_stock(int $productId, int $qty, array $context): void;
}
