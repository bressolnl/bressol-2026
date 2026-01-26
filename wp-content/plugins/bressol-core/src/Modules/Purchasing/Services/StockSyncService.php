<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Repositories\PurchaseOrderRepository;
use Bressol\Modules\Purchasing\Repositories\ReceivingRepository;
use Bressol\Modules\Purchasing\Services\Ports\StockWritePort;

if (!defined('ABSPATH')) {
    exit;
}

final class StockSyncService
{
    private const IDEMPOTENCY_PREFIX = 'bressol_purchasing_once_receiving_stock_';

    private Settings $settings;
    private IdempotencyStore $idempotencyStore;
    private ReceivingRepository $receivingRepository;
    private PurchaseOrderRepository $purchaseOrderRepository;
    private StockWritePort $stockWritePort;
    private AuditLogger $auditLogger;

    public function __construct(
        Settings $settings,
        IdempotencyStore $idempotencyStore,
        ReceivingRepository $receivingRepository,
        PurchaseOrderRepository $purchaseOrderRepository,
        StockWritePort $stockWritePort,
        AuditLogger $auditLogger
    ) {
        $this->settings = $settings;
        $this->idempotencyStore = $idempotencyStore;
        $this->receivingRepository = $receivingRepository;
        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->stockWritePort = $stockWritePort;
        $this->auditLogger = $auditLogger;
    }

    public function apply_receiving_stock(int $receivingId): void
    {
        if ($receivingId <= 0) {
            return;
        }

        if (!$this->settings->is_purchasing_stock_sync_enabled()) {
            return;
        }

        $receiving = $this->receivingRepository->get_receiving($receivingId);
        if (!$receiving) {
            return;
        }

        $poId = (int) ($receiving['po_id'] ?? 0);
        $idempotencyKey = self::IDEMPOTENCY_PREFIX . $receivingId;
        if ($this->idempotencyStore->was_done($idempotencyKey)) {
            $this->auditLogger->log('stock_sync_skipped_already_applied', [
                'receiving_id' => $receivingId,
                'po_id' => $poId,
            ], $receivingId, 'receiving');
            return;
        }

        $lines = $this->receivingRepository->get_receiving_lines($receivingId);
        if ($lines === []) {
            return;
        }

        $poLines = $poId > 0 ? $this->purchaseOrderRepository->get_po_lines($poId) : [];
        $poLineIndex = [];
        foreach ($poLines as $poLine) {
            $lineId = (int) ($poLine['id'] ?? 0);
            if ($lineId > 0) {
                $poLineIndex[$lineId] = $poLine;
            }
        }

        $linesAffected = 0;
        $qtyTotal = 0;

        foreach ($lines as $line) {
            $poLineId = (int) ($line['po_line_id'] ?? 0);
            $qty = (int) ($line['qty_received'] ?? 0);
            if ($poLineId <= 0 || $qty <= 0) {
                continue;
            }

            $poLine = $poLineIndex[$poLineId] ?? null;
            if (!$poLine) {
                $this->auditLogger->log('stock_sync_skipped_no_product', [
                    'receiving_id' => $receivingId,
                    'po_id' => $poId,
                    'po_line_id' => $poLineId,
                    'qty' => $qty,
                ], $receivingId, 'receiving');
                continue;
            }

            $productId = isset($poLine['product_id']) ? (int) $poLine['product_id'] : 0;
            $sku = isset($poLine['sku']) ? (string) $poLine['sku'] : '';
            if ($productId <= 0 && $sku !== '' && function_exists('wc_get_product_id_by_sku')) {
                $productId = (int) wc_get_product_id_by_sku($sku);
            }

            if ($productId <= 0) {
                $this->auditLogger->log('stock_sync_skipped_no_product', [
                    'receiving_id' => $receivingId,
                    'po_id' => $poId,
                    'po_line_id' => $poLineId,
                    'qty' => $qty,
                ], $receivingId, 'receiving');
                continue;
            }

            if (function_exists('wc_get_product')) {
                $product = wc_get_product($productId);
                if (!$product || !$product->managing_stock()) {
                    $this->auditLogger->log('stock_sync_skipped_manage_stock_disabled', [
                        'receiving_id' => $receivingId,
                        'po_id' => $poId,
                        'po_line_id' => $poLineId,
                        'qty' => $qty,
                    ], $receivingId, 'receiving');
                    continue;
                }
            }

            $this->stockWritePort->increase_stock($productId, $qty, [
                'receiving_id' => $receivingId,
                'po_id' => $poId,
                'po_line_id' => $poLineId,
                'qty' => $qty,
            ]);

            $linesAffected++;
            $qtyTotal += $qty;
        }

        $this->idempotencyStore->mark_done($idempotencyKey);

        $this->auditLogger->log('stock_sync_applied', [
            'receiving_id' => $receivingId,
            'po_id' => $poId,
            'line_count' => $linesAffected,
            'qty_total' => $qtyTotal,
        ], $receivingId, 'receiving');
    }
}
