<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Repositories\PurchaseOrderRepository;
use Bressol\Modules\Purchasing\Services\Ports\CostLedgerWritePort;

if (!defined('ABSPATH')) {
    exit;
}

final class PurchaseOrderService
{
    private PurchaseOrderRepository $repository;
    private CostLedgerWritePort $costLedgerPort;
    private AuditLogger $auditLogger;

    public function __construct(
        PurchaseOrderRepository $repository,
        CostLedgerWritePort $costLedgerPort,
        AuditLogger $auditLogger
    ) {
        $this->repository = $repository;
        $this->costLedgerPort = $costLedgerPort;
        $this->auditLogger = $auditLogger;
    }

    /** @return array<int, array<string, mixed>> */
    public function list_purchase_orders(): array
    {
        return $this->repository->list();
    }

    public function mark_received(int $purchaseOrderId): void
    {
        if ($purchaseOrderId <= 0) {
            return;
        }

        $this->costLedgerPort->record_purchase_receipt($purchaseOrderId, []);
        $this->auditLogger->log('po_received', [
            'po_id' => $purchaseOrderId,
            'result' => 'stub',
        ], $purchaseOrderId, 'purchase_order');
    }
}
