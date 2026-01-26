<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Repositories\PurchaseOrderRepository;
use Bressol\Modules\Purchasing\Repositories\ReceivingRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ReceivingService
{
    private ReceivingRepository $repository;
    private PurchaseOrderRepository $purchaseOrderRepository;
    private AuditLogger $auditLogger;
    private StockSyncService $stockSyncService;

    public function __construct(
        ReceivingRepository $repository,
        PurchaseOrderRepository $purchaseOrderRepository,
        AuditLogger $auditLogger,
        StockSyncService $stockSyncService
    )
    {
        $this->repository = $repository;
        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->auditLogger = $auditLogger;
        $this->stockSyncService = $stockSyncService;
    }

    /** @param array<int, array<string, mixed>> $lines
     *  @return int|\WP_Error
     */
    public function create_receiving(int $poId, ?string $receivedAtUtc, ?string $note, array $lines)
    {
        if ($poId <= 0) {
            return new \WP_Error('receiving_po_invalid', 'PO inválido.');
        }

        $previousCount = $this->repository->count_receivings_for_po($poId);
        $po = $this->purchaseOrderRepository->get_po($poId);
        if (!$po) {
            return new \WP_Error('receiving_po_missing', 'PO no encontrado.');
        }

        $receivedAt = $receivedAtUtc !== null && $receivedAtUtc !== '' ? $receivedAtUtc : gmdate('Y-m-d H:i:s');
        $noteValue = $this->sanitize_note($note ?? '');

        $poLines = $this->purchaseOrderRepository->get_po_lines($poId);
        if ($poLines === []) {
            return new \WP_Error('receiving_lines_missing', 'PO sin líneas.');
        }

        $poLineIndex = [];
        foreach ($poLines as $poLine) {
            $lineId = (int) ($poLine['id'] ?? 0);
            if ($lineId > 0) {
                $poLineIndex[$lineId] = $poLine;
            }
        }

        $existingTotals = $this->repository->get_received_totals_for_po($poId);
        $normalizedLines = [];
        foreach ($lines as $line) {
            $poLineId = isset($line['po_line_id']) ? (int) $line['po_line_id'] : 0;
            $qtyReceived = isset($line['qty_received']) ? (int) $line['qty_received'] : 0;
            if ($poLineId <= 0 || $qtyReceived <= 0) {
                continue;
            }
            if (!isset($poLineIndex[$poLineId])) {
                return new \WP_Error('receiving_line_invalid', 'Línea inválida.');
            }

            $orderedQty = (int) ($poLineIndex[$poLineId]['qty'] ?? 0);
            $prevReceived = isset($existingTotals[$poLineId]) ? (int) $existingTotals[$poLineId] : 0;
            if ($orderedQty <= 0 || ($prevReceived + $qtyReceived) > $orderedQty) {
                return new \WP_Error('receiving_qty_exceeds', 'Cantidad excede lo pendiente.');
            }

            $normalizedLines[] = [
                'po_line_id' => $poLineId,
                'qty_received' => $qtyReceived,
            ];
        }

        if ($normalizedLines === []) {
            return new \WP_Error('receiving_lines_empty', 'No hay líneas para recibir.');
        }

        $nowUtc = gmdate('Y-m-d H:i:s');
        $receivingId = $this->repository->insert_receiving([
            'po_id' => $poId,
            'received_at_utc' => $receivedAt,
            'note' => $noteValue !== '' ? $noteValue : null,
            'created_at_utc' => $nowUtc,
        ]);
        if ($receivingId <= 0) {
            return new \WP_Error('receiving_insert_failed', 'No se pudo crear la recepción.');
        }

        $linesWithMeta = [];
        foreach ($normalizedLines as $line) {
            $line['created_at_utc'] = $nowUtc;
            $linesWithMeta[] = $line;
        }

        $ok = $this->repository->insert_receiving_lines($receivingId, $linesWithMeta);
        if (!$ok) {
            $this->repository->delete_receiving($receivingId);
            return new \WP_Error('receiving_lines_failed', 'No se pudieron guardar las líneas.');
        }

        $this->auditLogger->log('receiving_created', [
            'receiving_id' => $receivingId,
            'po_id' => $poId,
            'received_at_utc' => $receivedAt,
            'line_count' => count($linesWithMeta),
        ], $receivingId, 'receiving');

        if ($previousCount === 0) {
            $this->auditLogger->log('po_locked_due_to_receiving', [
                'po_id' => $poId,
                'status' => (string) ($po['status'] ?? ''),
            ], $poId, 'purchase_order');
        }

        try {
            $this->stockSyncService->apply_receiving_stock($receivingId);
        } catch (\Throwable $exception) {
            $this->auditLogger->log('stock_sync_error', [
                'receiving_id' => $receivingId,
                'po_id' => $poId,
                'reason' => 'exception',
            ], $receivingId, 'receiving');
        }

        // TODO: Integrar con Inventory/Cost Ledger en v0.2.

        return $receivingId;
    }

    private function sanitize_note(string $note): string
    {
        $note = trim(wp_strip_all_tags($note));
        if (strlen($note) > 200) {
            $note = substr($note, 0, 200);
        }

        return $note;
    }
}
