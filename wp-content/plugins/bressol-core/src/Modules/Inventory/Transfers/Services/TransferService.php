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

            $existingAllocations = $this->count_allocations($transferId);
            if ($existingAllocations > 0) {
                throw new \RuntimeException('Transfer already shipped (allocations exist).');
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
                foreach ($allocations as $allocation) {
                    $lotQty = (int) $allocation['qty'];
                    $unitCogs = (int) ($allocation['unit_cogs_cents'] ?? 0);
                    $allocatedQty += $lotQty;
                    $weightedSum += ($lotQty * $unitCogs);
                }

                if ($allocatedQty !== $qty) {
                    throw new \RuntimeException('Insufficient ES stock for product ' . $productId . '.');
                }

                if ($line['unit_cogs_cents'] === null) {
                    $derivedUnitCogs = (int) round($weightedSum / $qty);
                    $this->lineRepository->update_unit_cogs($lineId, $derivedUnitCogs);
                }

                foreach ($allocations as $allocation) {
                    $lotId = (int) $allocation['lot_id'];
                    $lotQty = (int) $allocation['qty'];
                    $note = wp_json_encode([
                        'source' => 'transfer_ship',
                        'transfer_id' => $transferId,
                        'transfer_line_id' => $lineId,
                        'product_id' => $productId,
                        'lot_id' => $lotId,
                        'qty' => $lotQty,
                        'unit_cogs_cents' => (int) ($allocation['unit_cogs_cents'] ?? 0),
                        'expiry_date' => $allocation['expiry_date'] ?? null,
                    ]);
                    $this->lotService->decrement_lot(
                        $lotId,
                        $lotQty,
                        'transfer',
                        (string) $transferId,
                        $note ?: 'Transfer ship'
                    );

                $allocationId = $this->insert_allocation([
                    'transfer_id' => $transferId,
                    'transfer_line_id' => $lineId,
                    'product_id' => $productId,
                    'lot_id_es' => $lotId,
                    'qty_units' => $lotQty,
                    'unit_cogs_cents' => (int) ($allocation['unit_cogs_cents'] ?? 0),
                    'expiry_date' => $allocation['expiry_date'] ?? null,
                ]);
                if ($allocationId <= 0) {
                    throw new \RuntimeException('Failed to create transfer allocation.');
                }
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

            $allocations = $this->get_pending_transfer_allocations($transferId);
            if ($allocations === []) {
                throw new \RuntimeException('Transfer has no ship allocations.');
            }

            foreach ($allocations as $allocation) {
                $allocationId = (int) ($allocation['id'] ?? 0);
                $sourceLotId = (int) ($allocation['lot_id'] ?? 0);
                $qty = (int) ($allocation['qty'] ?? 0);
                $transferLineId = (int) ($allocation['transfer_line_id'] ?? 0);
                if ($sourceLotId <= 0 || $qty <= 0) {
                    throw new \RuntimeException('Invalid transfer allocation.');
                }

                $sourceLot = $this->lotRepository->get_lot_by_id($sourceLotId);
                if (!$sourceLot) {
                    throw new \RuntimeException('Source lot not found for transfer.');
                }

                $productId = (int) ($allocation['product_id'] ?? ($sourceLot['product_id'] ?? 0));
                if ($productId <= 0) {
                    throw new \RuntimeException('Missing product_id for allocation.');
                }

                $unitCogs = isset($allocation['unit_cogs_cents']) && is_numeric($allocation['unit_cogs_cents'])
                    ? (int) $allocation['unit_cogs_cents']
                    : (int) ($sourceLot['unit_cogs_cents'] ?? 0);
                if ($unitCogs <= 0) {
                    throw new \RuntimeException('Missing unit_cogs_cents for allocation.');
                }

                $expiryDate = isset($allocation['expiry_date']) && $allocation['expiry_date'] !== ''
                    ? (string) $allocation['expiry_date']
                    : ($sourceLot['expiry_date'] ?? null);

                $unitWeight = isset($sourceLot['unit_weight_grams']) ? (int) $sourceLot['unit_weight_grams'] : 0;

                $lotId = $this->lotRepository->create_lot([
                    'product_id' => $productId,
                    'location' => 'NL',
                    'qty_on_hand' => $qty,
                    'expiry_date' => $expiryDate,
                    'unit_cogs_cents' => $unitCogs,
                    'unit_weight_grams' => $unitWeight,
                    'source' => 'transfer',
                ]);

                if ($lotId <= 0) {
                    throw new \RuntimeException('Failed to create NL lot.');
                }

                if ($allocationId > 0) {
                    $updated = $this->mark_allocation_received($allocationId, $lotId);
                    if (!$updated) {
                        throw new \RuntimeException('Failed to update transfer allocation.');
                    }
                }

                $note = wp_json_encode([
                    'source' => 'transfer_receive',
                    'transfer_id' => $transferId,
                    'transfer_line_id' => $transferLineId,
                    'allocation_id' => $allocationId,
                    'source_lot_id' => $sourceLotId,
                    'qty' => $qty,
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

    private function insert_allocation(array $data): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_transfer_lot_allocations';
        $payload = [
            'transfer_id' => (int) ($data['transfer_id'] ?? 0),
            'transfer_line_id' => (int) ($data['transfer_line_id'] ?? 0),
            'product_id' => (int) ($data['product_id'] ?? 0),
            'lot_id_es' => (int) ($data['lot_id_es'] ?? 0),
            'qty_units' => (int) ($data['qty_units'] ?? 0),
            'unit_cogs_cents' => (int) ($data['unit_cogs_cents'] ?? 0),
            'expiry_date' => $data['expiry_date'] ?? null,
            'created_at' => current_time('mysql'),
        ];

        if (
            $payload['transfer_id'] <= 0
            || $payload['transfer_line_id'] <= 0
            || $payload['product_id'] <= 0
            || $payload['lot_id_es'] <= 0
            || $payload['qty_units'] <= 0
            || $payload['unit_cogs_cents'] <= 0
        ) {
            return 0;
        }

        $existingId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE transfer_id = %d AND transfer_line_id = %d AND lot_id_es = %d
             AND qty_units = %d AND unit_cogs_cents = %d AND expiry_date <=> %s
             LIMIT 1",
            $payload['transfer_id'],
            $payload['transfer_line_id'],
            $payload['lot_id_es'],
            $payload['qty_units'],
            $payload['unit_cogs_cents'],
            $payload['expiry_date']
        ));
        if (is_numeric($existingId) && (int) $existingId > 0) {
            return (int) $existingId;
        }

        $inserted = $wpdb->insert($table, $payload, [
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%d',
            '%s',
            '%s',
        ]);

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /** @return array<int, array<string, mixed>> */
    private function get_transfer_allocations(int $transferId): array
    {
        if ($transferId <= 0) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_transfer_lot_allocations';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, transfer_line_id, product_id, lot_id_es, lot_id_nl, qty_units, unit_cogs_cents, expiry_date
                 FROM {$table} WHERE transfer_id = %d ORDER BY id ASC",
                $transferId
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $allocations = [];
        foreach ($rows as $row) {
            $allocations[] = [
                'id' => (int) ($row['id'] ?? 0),
                'transfer_line_id' => (int) ($row['transfer_line_id'] ?? 0),
                'product_id' => (int) ($row['product_id'] ?? 0),
                'lot_id' => (int) ($row['lot_id_es'] ?? 0),
                'lot_id_nl' => isset($row['lot_id_nl']) ? (int) $row['lot_id_nl'] : 0,
                'qty' => (int) ($row['qty_units'] ?? 0),
                'unit_cogs_cents' => (int) ($row['unit_cogs_cents'] ?? 0),
                'expiry_date' => $row['expiry_date'] ?? null,
            ];
        }

        return $allocations;
    }

    /** @return array<int, array<string, mixed>> */
    private function get_pending_transfer_allocations(int $transferId): array
    {
        if ($transferId <= 0) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_transfer_lot_allocations';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, transfer_line_id, product_id, lot_id_es, lot_id_nl, qty_units, unit_cogs_cents, expiry_date
                 FROM {$table} WHERE transfer_id = %d AND lot_id_nl IS NULL ORDER BY id ASC",
                $transferId
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $allocations = [];
        foreach ($rows as $row) {
            $allocations[] = [
                'id' => (int) ($row['id'] ?? 0),
                'transfer_line_id' => (int) ($row['transfer_line_id'] ?? 0),
                'product_id' => (int) ($row['product_id'] ?? 0),
                'lot_id' => (int) ($row['lot_id_es'] ?? 0),
                'lot_id_nl' => isset($row['lot_id_nl']) ? (int) $row['lot_id_nl'] : 0,
                'qty' => (int) ($row['qty_units'] ?? 0),
                'unit_cogs_cents' => (int) ($row['unit_cogs_cents'] ?? 0),
                'expiry_date' => $row['expiry_date'] ?? null,
            ];
        }

        return $allocations;
    }

    private function count_allocations(int $transferId): int
    {
        if ($transferId <= 0) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_transfer_lot_allocations';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE transfer_id = %d",
            $transferId
        ));

        return $count;
    }

    private function mark_allocation_received(int $allocationId, int $lotIdNl): bool
    {
        if ($allocationId <= 0 || $lotIdNl <= 0) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_transfer_lot_allocations';
        $updated = $wpdb->update(
            $table,
            ['lot_id_nl' => $lotIdNl],
            ['id' => $allocationId],
            ['%d'],
            ['%d']
        );

        return $updated === 1;
    }
}
