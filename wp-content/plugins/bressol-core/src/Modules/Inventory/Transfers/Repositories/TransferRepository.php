<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Transfers\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class TransferRepository
{
    private const TABLE = 'bressol_stock_transfers';

    public function create_transfer(?int $createdBy, ?string $note): int
    {
        global $wpdb;

        $payload = [
            'status' => 'draft',
            'shipped_at' => null,
            'received_at' => null,
            'created_at' => current_time('mysql'),
            'created_by' => $createdBy !== null ? (int) $createdBy : null,
            'note' => $note !== null ? (string) $note : null,
        ];

        $inserted = $wpdb->insert($this->table(), $payload, [
            '%s',
            '%s',
            '%s',
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
    public function get_transfer(int $transferId, bool $forUpdate = false): ?array
    {
        if ($transferId <= 0) {
            return null;
        }

        global $wpdb;
        $table = $this->table();
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $sql = "SELECT * FROM {$table} WHERE id = %d{$lock}";

        $row = $wpdb->get_row($wpdb->prepare($sql, $transferId), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function set_status(int $transferId, string $status, ?string $shippedAt = null, ?string $receivedAt = null): bool
    {
        if ($transferId <= 0) {
            return false;
        }

        $status = sanitize_key($status);
        if (!in_array($status, ['draft', 'shipped', 'received', 'cancelled'], true)) {
            return false;
        }

        global $wpdb;

        $payload = [
            'status' => $status,
            'shipped_at' => $shippedAt,
            'received_at' => $receivedAt,
        ];

        $updated = $wpdb->update($this->table(), $payload, ['id' => $transferId], [
            '%s',
            '%s',
            '%s',
        ], ['%d']);

        return $updated !== false;
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }
}
