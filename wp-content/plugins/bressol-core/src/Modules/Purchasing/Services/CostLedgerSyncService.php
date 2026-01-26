<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Repositories\PurchaseOrderRepository;
use Bressol\Modules\Purchasing\Services\Ports\CostLedgerWritePort;

if (!defined('ABSPATH')) {
    exit;
}

final class CostLedgerSyncService
{
    private const IDEMPOTENCY_PREFIX = 'bressol_purchasing_once_po_costledger_';

    private Settings $settings;
    private IdempotencyStore $idempotencyStore;
    private PurchaseOrderRepository $purchaseOrderRepository;
    private CostLedgerWritePort $costLedgerWritePort;
    private AuditLogger $auditLogger;

    public function __construct(
        Settings $settings,
        IdempotencyStore $idempotencyStore,
        PurchaseOrderRepository $purchaseOrderRepository,
        CostLedgerWritePort $costLedgerWritePort,
        AuditLogger $auditLogger
    ) {
        $this->settings = $settings;
        $this->idempotencyStore = $idempotencyStore;
        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->costLedgerWritePort = $costLedgerWritePort;
        $this->auditLogger = $auditLogger;
    }

    public function apply_po_closed_to_cost_ledger(int $poId): void
    {
        if ($poId <= 0) {
            return;
        }

        if (!$this->settings->is_purchasing_cost_ledger_sync_enabled()) {
            return;
        }

        $idempotencyKey = self::IDEMPOTENCY_PREFIX . $poId;
        if ($this->idempotencyStore->was_done($idempotencyKey)) {
            $this->auditLogger->log('cost_ledger_sync_skipped_already_applied', [
                'po_id' => $poId,
            ], $poId, 'purchase_order');
            return;
        }

        $po = $this->purchaseOrderRepository->get_po($poId);
        if (!$po) {
            return;
        }

        $lines = $this->purchaseOrderRepository->get_po_lines($poId);
        if ($lines === []) {
            return;
        }

        $linesTotal = 0;
        foreach ($lines as $line) {
            $linesTotal += (int) ($line['line_total_excl_tax_cents'] ?? 0);
        }

        $customs = (int) ($po['customs_fees_cents'] ?? 0);
        $total = $linesTotal + max(0, $customs);
        $occurredAt = (string) ($po['received_at_utc'] ?? '');
        if ($occurredAt === '') {
            $occurredAt = gmdate('Y-m-d H:i:s');
        }

        $payload = [
            'source' => 'purchasing',
            'source_ref' => 'po:' . $poId,
            'occurred_at_utc' => $occurredAt,
            'currency' => 'EUR',
            'lines_excl_tax_cents' => $linesTotal,
            'customs_fees_cents' => $customs,
            'total_excl_tax_cents' => $total,
            'lines' => $this->build_line_payload($lines),
        ];

        $this->costLedgerWritePort->record_purchase($payload);

        $this->idempotencyStore->mark_done($idempotencyKey);
        $this->auditLogger->log('cost_ledger_sync_applied', [
            'po_id' => $poId,
            'line_count' => count($lines),
            'total_excl_tax_cents' => $total,
        ], $poId, 'purchase_order');
    }

    /** @param array<int, array<string, mixed>> $lines
     *  @return array<int, array<string, mixed>>
     */
    private function build_line_payload(array $lines): array
    {
        $payload = [];
        foreach ($lines as $line) {
            $payload[] = [
                'product_id' => isset($line['product_id']) ? (int) $line['product_id'] : null,
                'sku' => isset($line['sku']) ? (string) $line['sku'] : null,
                'qty' => isset($line['qty']) ? (int) $line['qty'] : 0,
                'unit_cost_excl_tax_cents' => isset($line['unit_cost_excl_tax_cents']) ? (int) $line['unit_cost_excl_tax_cents'] : 0,
                'line_total_excl_tax_cents' => isset($line['line_total_excl_tax_cents']) ? (int) $line['line_total_excl_tax_cents'] : 0,
            ];
        }

        return $payload;
    }
}
