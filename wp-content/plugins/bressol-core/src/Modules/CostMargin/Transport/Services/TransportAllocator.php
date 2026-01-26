<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Transport\Services;

use Bressol\Modules\CostMargin\Transport\Repositories\TransportAllocationRepository;
use Bressol\Modules\CostMargin\Transport\Repositories\TransportSnapshotRepository;
use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;
use Bressol\Modules\Inventory\Transfers\Repositories\TransferLineRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class TransportAllocator
{
    private TransportSnapshotRepository $snapshotRepository;
    private TransportAllocationRepository $allocationRepository;
    private TransferLineRepository $lineRepository;
    private LotRepository $lotRepository;

    public function __construct(
        ?TransportSnapshotRepository $snapshotRepository = null,
        ?TransportAllocationRepository $allocationRepository = null,
        ?TransferLineRepository $lineRepository = null,
        ?LotRepository $lotRepository = null
    ) {
        $this->snapshotRepository = $snapshotRepository ?? new TransportSnapshotRepository();
        $this->allocationRepository = $allocationRepository ?? new TransportAllocationRepository();
        $this->lineRepository = $lineRepository ?? new TransferLineRepository();
        $this->lotRepository = $lotRepository ?? new LotRepository();
    }

    public function create_snapshot(int $transferId, int $totalCostCents, ?int $createdBy = null, ?string $note = null): int
    {
        if ($totalCostCents < 0) {
            throw new \RuntimeException('Invalid total_cost_cents.');
        }

        $existing = $this->snapshotRepository->get_by_transfer_id($transferId);
        if ($existing) {
            throw new \RuntimeException('Snapshot already exists for transfer.');
        }

        $snapshotId = $this->snapshotRepository->create_snapshot($transferId, $totalCostCents, $createdBy, $note);
        if ($snapshotId <= 0) {
            throw new \RuntimeException('Unable to create transport snapshot.');
        }

        return $snapshotId;
    }

    public function recalculate_allocations(int $snapshotId): void
    {
        if ($snapshotId <= 0) {
            throw new \RuntimeException('Invalid snapshot id.');
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $snapshot = $this->snapshotRepository->get_by_id($snapshotId, true);
            if (!$snapshot) {
                throw new \RuntimeException('Snapshot not found.');
            }
            if (($snapshot['status'] ?? '') !== 'draft') {
                throw new \RuntimeException('Snapshot is not in draft status.');
            }

            $transferId = (int) ($snapshot['transfer_id'] ?? 0);
            $totalCost = (int) ($snapshot['total_cost_cents'] ?? 0);
            if ($transferId <= 0) {
                throw new \RuntimeException('Invalid transfer reference.');
            }

            $lines = $this->lineRepository->get_lines($transferId);
            $lineById = [];
            foreach ($lines as $line) {
                $lineId = (int) ($line['id'] ?? 0);
                if ($lineId > 0) {
                    $lineById[$lineId] = $line;
                }
            }

            $moves = $this->get_receipt_moves($transferId);
            if ($moves === []) {
                throw new \RuntimeException('No receipt moves found for transfer.');
            }

            $allocations = [];
            $totalWeight = 0;

            foreach ($moves as $move) {
                $lotId = (int) $move['lot_id'];
                $qty = (int) $move['qty'];
                $lineId = (int) $move['transfer_line_id'];
                if ($lotId <= 0 || $qty <= 0 || $lineId <= 0) {
                    throw new \RuntimeException('Invalid receipt move data.');
                }

                $line = $lineById[$lineId] ?? null;
                if (!$line) {
                    throw new \RuntimeException('Transfer line not found for allocation.');
                }

                $weightTotal = $this->resolve_weight_total($qty, $line, $lotId);
                if ($weightTotal <= 0) {
                    throw new \RuntimeException('Invalid weight total for allocation.');
                }

                $allocations[] = [
                    'transfer_line_id' => $lineId,
                    'lot_id_nl' => $lotId,
                    'qty_units' => $qty,
                    'weight_total_grams' => $weightTotal,
                    'allocated_cost_cents' => 0,
                ];
                $totalWeight += $weightTotal;
            }

            if ($totalWeight <= 0) {
                throw new \RuntimeException('Total weight is zero.');
            }

            $allocatedSum = 0;
            $lastIndex = count($allocations) - 1;
            foreach ($allocations as $index => $allocation) {
                $weight = (int) $allocation['weight_total_grams'];
                $cost = (int) round($totalCost * ($weight / $totalWeight));
                if ($index === $lastIndex) {
                    $cost = $totalCost - $allocatedSum;
                }
                $allocations[$index]['allocated_cost_cents'] = $cost;
                $allocatedSum += $cost;
            }

            $this->allocationRepository->replace_allocations($snapshotId, $allocations);

            $wpdb->query('COMMIT');
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    public function close_snapshot(int $snapshotId, ?int $closedBy = null): void
    {
        if ($snapshotId <= 0) {
            throw new \RuntimeException('Invalid snapshot id.');
        }

        $snapshot = $this->snapshotRepository->get_by_id($snapshotId);
        if (!$snapshot) {
            throw new \RuntimeException('Snapshot not found.');
        }
        if (($snapshot['status'] ?? '') !== 'draft') {
            throw new \RuntimeException('Snapshot is already closed.');
        }

        $this->snapshotRepository->set_status($snapshotId, 'closed', current_time('mysql'), $closedBy);
    }

    /** @return array<int, array<string, mixed>> */
    public function get_allocations(int $snapshotId): array
    {
        return $this->allocationRepository->get_allocations($snapshotId);
    }

    /** @return array<int, array{lot_id:int,qty:int,transfer_line_id:int}> */
    private function get_receipt_moves(int $transferId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_lot_moves';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT lot_id, qty, note FROM {$table} WHERE ref_type = %s AND ref_id = %s AND type = %s ORDER BY id ASC",
            'transfer',
            (string) $transferId,
            'receipt'
        ), ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $moves = [];
        foreach ($rows as $row) {
            $note = (string) ($row['note'] ?? '');
            $transferLineId = $this->extract_transfer_line_id($note);
            $moves[] = [
                'lot_id' => (int) ($row['lot_id'] ?? 0),
                'qty' => (int) ($row['qty'] ?? 0),
                'transfer_line_id' => $transferLineId,
            ];
        }

        return $moves;
    }

    /** @param array<string, mixed> $line */
    private function resolve_weight_total(int $qty, array $line, int $lotId): int
    {
        $lineWeight = isset($line['line_weight_total_grams']) ? (int) $line['line_weight_total_grams'] : 0;
        if ($lineWeight > 0) {
            return $lineWeight;
        }

        $override = isset($line['unit_weight_override_grams']) ? (int) $line['unit_weight_override_grams'] : 0;
        if ($override > 0) {
            return $qty * $override;
        }

        $lot = $this->lotRepository->get_lot_by_id($lotId);
        $unitWeight = $lot ? (int) ($lot['unit_weight_grams'] ?? 0) : 0;
        return $qty * max(0, $unitWeight);
    }

    private function extract_transfer_line_id(string $note): int
    {
        if ($note === '') {
            return 0;
        }

        $decoded = json_decode($note, true);
        if (is_array($decoded) && isset($decoded['transfer_line_id'])) {
            return (int) $decoded['transfer_line_id'];
        }

        return 0;
    }
}
