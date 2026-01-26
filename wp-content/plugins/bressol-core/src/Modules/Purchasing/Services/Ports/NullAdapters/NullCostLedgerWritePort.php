<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\NullAdapters;

use Bressol\Modules\Purchasing\Services\Ports\CostLedgerWritePort;

if (!defined('ABSPATH')) {
    exit;
}

final class NullCostLedgerWritePort implements CostLedgerWritePort
{
    public function record_purchase_receipt(int $purchaseOrderId, array $payload): void
    {
    }
}
