<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Transfers\Services;

use Bressol\Modules\Inventory\Lots\Repositories\LotMoveRepository;
use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;
use Bressol\Modules\Inventory\Lots\Services\LotService;
use Bressol\Modules\Inventory\Transfers\Repositories\TransferLineRepository;
use Bressol\Modules\Inventory\Transfers\Repositories\TransferRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class TransferService
{
    private TransferRepository $transferRepository;
    private TransferLineRepository $lineRepository;
    private LotService $lotService;
    private LotRepository $lotRepository;
    private LotMoveRepository $lotMoveRepository;

    public function __construct(
        ?TransferRepository $transferRepository = null,
        ?TransferLineRepository $lineRepository = null,
        ?LotService $lotService = null,
        ?LotRepository $lotRepository = null,
        ?LotMoveRepository $lotMoveRepository = null
    ) {
        $this->transferRepository = $transferRepository ?? new TransferRepository();
        $this->lineRepository = $lineRepository ?? new TransferLineRepository();
        $this->lotService = $lotService ?? new LotService();
        $this->lotRepository = $lotRepository ?? new LotRepository();
        $this->lotMoveRepository = $lotMoveRepository ?? new LotMoveRepository();
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    public function create_transfer(array $lines, ?int $createdBy = null, ?string $note = null): int
    {
        if ($lines === []) {
            throw new \RuntimeException('Transfer requires at least one line.');
        }

        $transferId = $this->transferRepository->create_transfer($createdBy, $note);
        if ($transferId <= 0) {
            throw new \RuntimeException('Unable to create transfer.');
        }

        foreach ($lines as $line) {
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $qty = isset($line['qty_units']) ? (int) $line['qty_units'] : 0;
            $expiryDate = $line['expiry_date'] ?? null;
            $unitCogs = isset($line['unit_cogs_cents']) ? (int) $line['unit_cogs_cents'] : null;
            $unitWeightOverride = isset($line['unit_weight_override_grams'])
                ? (int) $line['unit_weight_override_grams']
                : null;
            $lineWeightTotal = isset($line['line_weight_total_grams'])
                ? (int) $line['line_weight_total_grams']
                : null;

            $lineId = $this->lineRepository->add_line(
                $transferId,
                $productId,
                $qty,
                $expiryDate,
                $unitCogs,
                $unitWeightOverride,
                $lineWeightTotal
            );

            if ($lineId <= 0) {
                throw new \RuntimeException('Failed to add transfer line.');
            }
        }

        return $transferId;
    }

    public function ship(int $transferId): void
    {
        if ($transferId <= 0) {
            throw new \RuntimeException('Invalid transfer id.');
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $transfer = $this->transferRepository->get_transfer($transferId, true);
            if (!$transfer) {
                throw new \RuntimeException('Transfer not found.');
            }
            if (($transfer['status'] ?? '') !== 'draft') {
                throw new \RuntimeException('Transfer is not in draft status.');
            }

            $lines = $this->lineRepository->get_lines($transferId);
            if ($lines === []) {
                throw new \RuntimeException('Transfer has no lines.');
            }

            foreach ($lines as $line) {
                $lineId = (int) ($line['id'] ?? 0);
                $productId = (int) ($line['product_id'] ?? 0);
                $qty = (int) ($line['qty_units'] ?? 0);
                if ($lineId <= 0 || $productId <= 0 || $qty <= 0) {
                    throw new \RuntimeException('Invalid transfer line.');
                }

                $allocations = $this->lotService->allocate_fifo($productId, 'ES', $qty, false, null);
                if ($allocations === []) {
                    throw new \RuntimeException('Insufficient ES stock for product ' . $productId . '.');
                }

                $allocatedQty = 0;
                $weightedSum = 0;
                $allocationMeta = [];
                foreach ($allocations as $allocation) {
                    $lotQty = (int) $allocation['qty'];
                    $unitCogs = (int) ($allocation['unit_cogs_cents'] ?? 0);
                    $allocatedQty += $lotQty;
                    $weightedSum += ($lotQty * $unitCogs);
                    $allocationMeta[] = [
                        'lot_id' => (int) $allocation['lot_id'],
                        'qty' => $lotQty,
                        'unit_cogs_cents' => $unitCogs,
                    ];
                }

                if ($allocatedQty !== $qty) {
                    throw new \RuntimeException('Insufficient ES stock for product ' . $productId . '.');
                }

                if ($line['unit_cogs_cents'] === null) {
                    $derivedUnitCogs = (int) round($weightedSum / $qty);
                    $this->lineRepository->update_unit_cogs($lineId, $derivedUnitCogs);
                }

                $note = wp_json_encode([
                    'transfer_id' => $transferId,
                    'transfer_line_id' => $lineId,
                    'allocations' => $allocationMeta,
                ]);

                foreach ($allocations as $allocation) {
                    $lotId = (int) $allocation['lot_id'];
                    $lotQty = (int) $allocation['qty'];
                    $this->lotService->decrement_lot(
                        $lotId,
                        $lotQty,
                        'transfer',
                        (string) $transferId,
                        $note ?: 'Transfer ship'
                    );
                }
            }

            $this->transferRepository->set_status($transferId, 'shipped', current_time('mysql'), null);

            $wpdb->query('COMMIT');
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    public function receive(int $transferId): void
    {
        if ($transferId <= 0) {
            throw new \RuntimeException('Invalid transfer id.');
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $transfer = $this->transferRepository->get_transfer($transferId, true);
            if (!$transfer) {
                throw new \RuntimeException('Transfer not found.');
            }
            if (($transfer['status'] ?? '') !== 'shipped') {
                throw new \RuntimeException('Transfer is not in shipped status.');
            }

            $movesTable = $wpdb->prefix . 'bressol_lot_moves';
            $receiptCount = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$movesTable} WHERE ref_type = %s AND ref_id = %s AND type = %s",
                'transfer',
                (string) $transferId,
                'receipt'
            ));
            if ($receiptCount > 0) {
                throw new \RuntimeException('Transfer already received.');
            }

            $lines = $this->lineRepository->get_lines($transferId);
            if ($lines === []) {
                throw new \RuntimeException('Transfer has no lines.');
            }

            foreach ($lines as $line) {
                $lineId = (int) ($line['id'] ?? 0);
                $productId = (int) ($line['product_id'] ?? 0);
                $qty = (int) ($line['qty_units'] ?? 0);
                if ($lineId <= 0 || $productId <= 0 || $qty <= 0) {
                    throw new \RuntimeException('Invalid transfer line.');
                }

                $unitCogs = $line['unit_cogs_cents'] !== null ? (int) $line['unit_cogs_cents'] : null;
                if ($unitCogs === null) {
                    throw new \RuntimeException('Missing unit_cogs_cents for transfer line.');
                }

                $unitWeight = $this->resolve_unit_weight($productId, $qty, $line);

                $lotId = $this->lotRepository->create_lot([
                    'product_id' => $productId,
                    'location' => 'NL',
                    'qty_on_hand' => $qty,
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'unit_cogs_cents' => $unitCogs,
                    'unit_weight_grams' => $unitWeight,
                    'source' => 'transfer',
                ]);

                if ($lotId <= 0) {
                    throw new \RuntimeException('Failed to create NL lot.');
                }

                $note = wp_json_encode([
                    'transfer_line_id' => $lineId,
                ]);

                $this->lotMoveRepository->add_move(
                    $lotId,
                    'receipt',
                    $qty,
                    'transfer',
                    (string) $transferId,
                    $note ?: 'Transfer receive'
                );
            }

            $this->transferRepository->set_status($transferId, 'received', null, current_time('mysql'));

            $wpdb->query('COMMIT');
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    /** @param array<string, mixed> $line */
    private function resolve_unit_weight(int $productId, int $qty, array $line): int
    {
        $lineWeightTotal = $line['line_weight_total_grams'] ?? null;
        if ($lineWeightTotal !== null) {
            $total = (int) $lineWeightTotal;
            if ($total > 0 && $qty > 0) {
                return (int) round($total / $qty);
            }
        }

        $override = $line['unit_weight_override_grams'] ?? null;
        if ($override !== null) {
            return max(0, (int) $override);
        }

        $meta = get_post_meta($productId, '_bressol_unit_weight_grams', true);
        return is_numeric($meta) ? max(0, (int) $meta) : 0;
    }
}
