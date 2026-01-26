<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Transport\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class TransportAllocationRepository
{
    private const TABLE = 'bressol_transport_allocations';

    /**
     * @param array<int, array<string, mixed>> $allocations
     */
    public function replace_allocations(int $snapshotId, array $allocations): int
    {
        if ($snapshotId <= 0) {
            return 0;
        }

        global $wpdb;
        $table = $this->table();

        $wpdb->delete($table, ['snapshot_id' => $snapshotId], ['%d']);

        $insertedCount = 0;
        foreach ($allocations as $allocation) {
            $payload = [
                'snapshot_id' => $snapshotId,
                'transfer_line_id' => (int) ($allocation['transfer_line_id'] ?? 0),
                'lot_id_nl' => (int) ($allocation['lot_id_nl'] ?? 0),
                'qty_units' => (int) ($allocation['qty_units'] ?? 0),
                'weight_total_grams' => (int) ($allocation['weight_total_grams'] ?? 0),
                'allocated_cost_cents' => (int) ($allocation['allocated_cost_cents'] ?? 0),
                'created_at' => current_time('mysql'),
            ];

            if ($payload['transfer_line_id'] <= 0 || $payload['lot_id_nl'] <= 0 || $payload['qty_units'] <= 0) {
                continue;
            }

            $inserted = $wpdb->insert($table, $payload, [
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
            ]);

            if ($inserted !== false) {
                $insertedCount++;
            }
        }

        return $insertedCount;
    }

    /** @return array<int, array<string, mixed>> */
    public function get_allocations(int $snapshotId): array
    {
        if ($snapshotId <= 0) {
            return [];
        }

        global $wpdb;
        $table = $this->table();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE snapshot_id = %d ORDER BY id ASC", $snapshotId),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function get_unit_transport_cents_for_lot(int $lotId): int
    {
        if ($lotId <= 0) {
            return 0;
        }

        global $wpdb;
        $allocations = $this->table();
        $snapshots = $wpdb->prefix . 'bressol_transport_snapshots';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(a.allocated_cost_cents) AS total_cost, SUM(a.qty_units) AS total_qty
             FROM {$allocations} a
             INNER JOIN {$snapshots} s ON s.id = a.snapshot_id
             WHERE a.lot_id_nl = %d AND s.status = %s AND a.qty_units > 0 AND a.allocated_cost_cents >= 0",
            $lotId,
            'closed'
        ), ARRAY_A);

        if (!is_array($row)) {
            return 0;
        }

        $totalCost = is_numeric($row['total_cost'] ?? null) ? (int) $row['total_cost'] : 0;
        $totalQty = is_numeric($row['total_qty'] ?? null) ? (int) $row['total_qty'] : 0;

        if ($totalQty <= 0) {
            return 0;
        }

        return (int) round($totalCost / $totalQty);
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }
}
