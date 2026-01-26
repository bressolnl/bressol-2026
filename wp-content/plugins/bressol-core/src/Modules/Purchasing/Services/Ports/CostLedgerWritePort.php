<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface CostLedgerWritePort
{
    /** @param array<string, mixed> $payload */
    public function record_purchase(array $payload): void;
}
