<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Domain\Enum\Status;
use Bressol\Modules\Purchasing\Repositories\SupplierRepository;
use Bressol\Modules\Purchasing\Repositories\PurchaseOrderRepository;
use Bressol\Modules\Purchasing\Repositories\ReceivingRepository;
use Bressol\Modules\Purchasing\Services\Ports\CostLedgerWritePort;

if (!defined('ABSPATH')) {
    exit;
}

final class PurchaseOrderService
{
    private PurchaseOrderRepository $repository;
    private SupplierRepository $supplierRepository;
    private ReceivingRepository $receivingRepository;
    private CostLedgerWritePort $costLedgerPort;
    private AuditLogger $auditLogger;

    public function __construct(
        PurchaseOrderRepository $repository,
        SupplierRepository $supplierRepository,
        ReceivingRepository $receivingRepository,
        CostLedgerWritePort $costLedgerPort,
        AuditLogger $auditLogger
    ) {
        $this->repository = $repository;
        $this->supplierRepository = $supplierRepository;
        $this->receivingRepository = $receivingRepository;
        $this->costLedgerPort = $costLedgerPort;
        $this->auditLogger = $auditLogger;
    }

    /** @param array<string, mixed> $filters
     *  @return array<int, array<string, mixed>>
     */
    public function list_purchase_orders(array $filters = []): array
    {
        return $this->repository->list_pos(50, 0, $filters);
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

    /** @param array<string, mixed> $header
     *  @param array<int, array<string, mixed>> $lines
     *  @return int|true|\WP_Error
     */
    public function create_or_update_po(?int $id, array $header, array $lines, bool $allowLineUpdate = true)
    {
        $normalizedHeader = $this->validate_normalize_po_header($header);
        if ($normalizedHeader instanceof \WP_Error) {
            return $normalizedHeader;
        }

        $nowUtc = gmdate('Y-m-d H:i:s');

        if ($id === null || $id <= 0) {
            if (!$allowLineUpdate) {
                return new \WP_Error('po_lines_required', 'Líneas requeridas.');
            }

            $normalizedLines = $this->validate_normalize_lines($lines);
            if ($normalizedLines instanceof \WP_Error) {
                return $normalizedLines;
            }

            $totals = $this->compute_totals($normalizedLines, (int) $normalizedHeader['customs_fees_cents']);

            if ($normalizedHeader['po_number'] !== null) {
                $existingNumber = $this->repository->get_po_by_number((string) $normalizedHeader['po_number']);
                if ($existingNumber) {
                    return new \WP_Error('po_number_taken', 'PO number ya existe.');
                }
            }

            $normalizedHeader['created_at_utc'] = $nowUtc;
            $normalizedHeader['updated_at_utc'] = $nowUtc;
            $poId = $this->repository->insert_po($normalizedHeader);
            if ($poId <= 0) {
                return new \WP_Error('po_insert_failed', 'No se pudo guardar el PO.');
            }

            $linesWithMeta = $this->lines_with_meta($normalizedLines, $nowUtc);
            if (!$this->repository->replace_po_lines($poId, $linesWithMeta)) {
                return new \WP_Error('po_lines_failed', 'No se pudieron guardar las líneas.');
            }

            $this->auditLogger->log('po_created', [
                'po_id' => $poId,
                'supplier_id' => $normalizedHeader['supplier_id'],
                'status' => $normalizedHeader['status'],
                'totals_cents' => $totals['total_excl_tax_cents'],
            ], $poId, 'purchase_order');

            return $poId;
        }

        $existing = $this->repository->get_po($id);
        if (!$existing) {
            return new \WP_Error('po_not_found', 'PO no encontrado.');
        }

        if ($allowLineUpdate && $this->receivingRepository->count_receivings_for_po($id) > 0) {
            return new \WP_Error('po_locked_has_receivings', 'PO bloqueado por recepciones.');
        }

        if ($normalizedHeader['po_number'] !== null) {
            $existingNumber = $this->repository->get_po_by_number((string) $normalizedHeader['po_number']);
            if ($existingNumber && (int) $existingNumber['id'] !== $id) {
                return new \WP_Error('po_number_taken', 'PO number ya existe.');
            }
        }

        $normalizedHeader['status'] = (string) $existing['status'];
        $normalizedHeader['updated_at_utc'] = $nowUtc;
        $ok = $this->repository->update_po($id, $normalizedHeader);
        if (!$ok) {
            return new \WP_Error('po_update_failed', 'No se pudo actualizar el PO.');
        }

        $linesWithMeta = [];
        if ($allowLineUpdate) {
            $normalizedLines = $this->validate_normalize_lines($lines);
            if ($normalizedLines instanceof \WP_Error) {
                return $normalizedLines;
            }
            $linesWithMeta = $this->lines_with_meta($normalizedLines, $nowUtc);
            if (!$this->repository->replace_po_lines($id, $linesWithMeta)) {
                return new \WP_Error('po_lines_failed', 'No se pudieron guardar las líneas.');
            }
        } else {
            $linesWithMeta = $this->repository->get_po_lines($id);
        }

        $totals = $this->compute_totals($linesWithMeta, (int) $normalizedHeader['customs_fees_cents']);
        $this->auditLogger->log('po_updated', [
            'po_id' => $id,
            'supplier_id' => $normalizedHeader['supplier_id'],
            'status' => $normalizedHeader['status'],
            'totals_cents' => $totals['total_excl_tax_cents'],
        ], $id, 'purchase_order');

        return true;
    }

    public function change_status(int $id, string $newStatus)
    {
        if ($id <= 0) {
            return new \WP_Error('po_invalid', 'PO inválido.');
        }

        $newStatus = strtolower(trim($newStatus));
        if (!in_array($newStatus, Status::all(), true)) {
            return new \WP_Error('po_status_invalid', 'Estado inválido.');
        }

        $po = $this->repository->get_po($id);
        if (!$po) {
            return new \WP_Error('po_not_found', 'PO no encontrado.');
        }

        $current = (string) $po['status'];
        if (in_array($current, [Status::CLOSED, Status::CANCELLED], true) && $newStatus !== $current) {
            return new \WP_Error('po_status_locked', 'Estado bloqueado.');
        }

        $oldStatus = $this->repository->update_status($id, $newStatus);
        if ($oldStatus === null) {
            return new \WP_Error('po_status_failed', 'No se pudo actualizar el estado.');
        }

        $lines = $this->repository->get_po_lines($id);
        $totals = $this->compute_totals($lines, (int) ($po['customs_fees_cents'] ?? 0));
        $this->auditLogger->log('po_status_changed', [
            'po_id' => $id,
            'supplier_id' => (int) ($po['supplier_id'] ?? 0),
            'status' => $newStatus,
            'from' => $oldStatus,
            'to' => $newStatus,
            'totals_cents' => $totals['total_excl_tax_cents'],
        ], $id, 'purchase_order');

        return true;
    }

    /** @param array<string, mixed> $header
     *  @return array<string, mixed>|\WP_Error
     */
    private function validate_normalize_po_header(array $header)
    {
        $supplierId = isset($header['supplier_id']) ? (int) $header['supplier_id'] : 0;
        if ($supplierId <= 0 || !$this->supplierRepository->get_supplier($supplierId)) {
            return new \WP_Error('po_supplier_invalid', 'Proveedor inválido.');
        }

        $status = isset($header['status']) ? strtolower((string) $header['status']) : Status::DRAFT;
        if (!in_array($status, Status::all(), true)) {
            return new \WP_Error('po_status_invalid', 'Estado inválido.');
        }

        $poNumberRaw = isset($header['po_number']) ? sanitize_text_field((string) $header['po_number']) : '';
        $poNumber = strtoupper(trim($poNumberRaw));
        if ($poNumber !== '' && (strlen($poNumber) < 2 || strlen($poNumber) > 64)) {
            return new \WP_Error('po_number_invalid', 'PO number inválido.');
        }

        $customsRaw = isset($header['customs_fees_cents']) ? (int) $header['customs_fees_cents'] : 0;
        if ($customsRaw < 0) {
            return new \WP_Error('po_customs_invalid', 'Customs inválido.');
        }

        $taxRate = null;
        if (isset($header['tax_rate_bp']) && $header['tax_rate_bp'] !== '' && $header['tax_rate_bp'] !== null) {
            $taxRate = (int) $header['tax_rate_bp'];
            if ($taxRate < 0 || $taxRate > 10000) {
                return new \WP_Error('po_tax_invalid', 'Tax rate inválido.');
            }
        }

        $warehouseRaw = isset($header['warehouse_code']) ? sanitize_text_field((string) $header['warehouse_code']) : '';
        $warehouse = strtoupper(trim($warehouseRaw));
        if ($warehouse === '') {
            $warehouse = 'ALICANTE';
        }
        if (strlen($warehouse) > 40) {
            $warehouse = substr($warehouse, 0, 40);
        }

        return [
            'po_number' => $poNumber === '' ? null : $poNumber,
            'supplier_id' => $supplierId,
            'status' => $status,
            'customs_fees_cents' => $customsRaw,
            'currency' => 'EUR',
            'tax_rate_bp' => $taxRate,
            'warehouse_code' => $warehouse,
        ];
    }

    /** @param array<int, array<string, mixed>> $lines
     *  @return array<int, array<string, mixed>>|\WP_Error
     */
    private function validate_normalize_lines(array $lines)
    {
        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $qty = isset($line['qty']) ? (int) $line['qty'] : 0;
            $unitCost = isset($line['unit_cost_excl_tax_cents']) ? (int) $line['unit_cost_excl_tax_cents'] : -1;
            $sku = isset($line['sku']) ? sanitize_text_field((string) $line['sku']) : '';
            $sku = trim($sku);

            if ($qty <= 0) {
                continue;
            }
            if ($unitCost < 0) {
                return new \WP_Error('po_line_cost_invalid', 'Coste inválido.');
            }

            $normalized[] = [
                'product_id' => isset($line['product_id']) ? (int) $line['product_id'] : null,
                'sku' => $sku === '' ? null : $sku,
                'qty' => $qty,
                'unit_cost_excl_tax_cents' => $unitCost,
                'line_total_excl_tax_cents' => $qty * $unitCost,
            ];
        }

        if ($normalized === []) {
            return new \WP_Error('po_lines_empty', 'Líneas vacías.');
        }

        return $normalized;
    }

    /** @param array<int, array<string, mixed>> $lines
     *  @return array{lines_excl_tax_cents:int,total_excl_tax_cents:int}
     */
    private function compute_totals(array $lines, int $customsCents): array
    {
        $linesTotal = 0;
        foreach ($lines as $line) {
            $linesTotal += (int) $line['line_total_excl_tax_cents'];
        }

        $total = $linesTotal + max(0, $customsCents);

        return [
            'lines_excl_tax_cents' => $linesTotal,
            'total_excl_tax_cents' => $total,
        ];
    }

    /** @param array<int, array<string, mixed>> $lines
     *  @return array<int, array<string, mixed>>
     */
    private function lines_with_meta(array $lines, string $createdAtUtc): array
    {
        $withMeta = [];
        foreach ($lines as $line) {
            $line['created_at_utc'] = $createdAtUtc;
            $withMeta[] = $line;
        }

        return $withMeta;
    }
}
