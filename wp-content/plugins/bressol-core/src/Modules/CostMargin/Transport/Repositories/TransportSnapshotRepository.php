<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Transport\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class TransportSnapshotRepository
{
    private const TABLE = 'bressol_transport_snapshots';

    public function create_snapshot(int $transferId, int $totalCostCents, ?int $createdBy, ?string $note): int
    {
        if ($transferId <= 0 || $totalCostCents < 0) {
            return 0;
        }

        global $wpdb;

        $payload = [
            'transfer_id' => $transferId,
            'total_cost_cents' => $totalCostCents,
            'method' => 'weight',
            'status' => 'draft',
            'created_at' => current_time('mysql'),
            'created_by' => $createdBy !== null ? (int) $createdBy : null,
            'closed_at' => null,
            'closed_by' => null,
            'note' => $note !== null ? (string) $note : null,
        ];

        $inserted = $wpdb->insert($this->table(), $payload, [
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%d',
            '%s',
        ]);

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /** @return array<string, mixed>|null */
    public function get_by_transfer_id(int $transferId): ?array
    {
        if ($transferId <= 0) {
            return null;
        }

        global $wpdb;
        $table = $this->table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE transfer_id = %d", $transferId), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function get_by_id(int $snapshotId, bool $forUpdate = false): ?array
    {
        if ($snapshotId <= 0) {
            return null;
        }

        global $wpdb;
        $table = $this->table();
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d{$lock}", $snapshotId), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function set_status(int $snapshotId, string $status, ?string $closedAt = null, ?int $closedBy = null): bool
    {
        if ($snapshotId <= 0) {
            return false;
        }

        $status = sanitize_key($status);
        if (!in_array($status, ['draft', 'closed'], true)) {
            return false;
        }

        global $wpdb;
        $payload = [
            'status' => $status,
            'closed_at' => $closedAt,
            'closed_by' => $closedBy,
        ];

        $updated = $wpdb->update($this->table(), $payload, ['id' => $snapshotId], [
            '%s',
            '%s',
            '%d',
        ], ['%d']);

        return $updated !== false;
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }
}
