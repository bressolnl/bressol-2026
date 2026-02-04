<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Repositories;

use Bressol\Modules\Forecasting\Schema;

if (!defined('ABSPATH')) {
    exit;
}

final class ForecastSnapshotRepository
{
    public function upsert_manual_snapshot_for_event(
        int $eventId,
        string $snapshotAtUtc,
        ?int $createdBy,
        ?string $note
    ): int {
        if ($eventId <= 0 || $snapshotAtUtc === '') {
            return 0;
        }

        $latest = $this->get_latest_snapshot_for_event($eventId);
        if (is_array($latest) && (string) ($latest['source'] ?? '') === 'manual') {
            $snapshotId = (int) ($latest['id'] ?? 0);
            if ($snapshotId > 0) {
                $this->update_snapshot($snapshotId, $snapshotAtUtc, $createdBy, $note);
                return $snapshotId;
            }
        }

        return $this->create_snapshot($eventId, 'manual', $snapshotAtUtc, $createdBy, $note);
    }

    public function create_snapshot(
        int $eventId,
        string $source,
        string $snapshotAtUtc,
        ?int $createdBy,
        ?string $note
    ): int {
        if ($eventId <= 0 || $snapshotAtUtc === '') {
            return 0;
        }

        global $wpdb;
        $payload = [
            'event_id' => $eventId,
            'source' => $source !== '' ? $source : 'manual',
            'status' => 'final',
            'snapshot_at_utc' => $snapshotAtUtc,
            'created_at_utc' => current_time('mysql', true),
            'created_by' => $createdBy !== null ? (int) $createdBy : null,
            'note' => $note !== null ? (string) $note : null,
        ];

        $inserted = $wpdb->insert($this->snapshots_table(), $payload, [
            '%d',
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

    /** @param array<int, array{product_id:int,qty_units:int}> $lines */
    public function replace_lines(int $snapshotId, array $lines): void
    {
        if ($snapshotId <= 0) {
            return;
        }

        global $wpdb;
        $wpdb->delete($this->lines_table(), ['snapshot_id' => $snapshotId], ['%d']);

        $nowUtc = current_time('mysql', true);
        foreach ($lines as $line) {
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $qty = isset($line['qty_units']) ? (int) $line['qty_units'] : 0;
            if ($productId <= 0 || $qty < 0) {
                continue;
            }

            $wpdb->insert($this->lines_table(), [
                'snapshot_id' => $snapshotId,
                'product_id' => $productId,
                'qty_units' => $qty,
                'created_at_utc' => $nowUtc,
            ], ['%d', '%d', '%d', '%s']);
        }
    }

    /** @return array<string, mixed>|null */
    public function get_latest_snapshot_for_event(int $eventId): ?array
    {
        if ($eventId <= 0) {
            return null;
        }

        global $wpdb;
        $table = $this->snapshots_table();
        $sql = "SELECT * FROM {$table} WHERE event_id = %d ORDER BY snapshot_at_utc DESC, id DESC LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, $eventId), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function get_latest_snapshot_for_event_and_source(int $eventId, string $source): ?array
    {
        if ($eventId <= 0 || $source === '') {
            return null;
        }

        global $wpdb;
        $table = $this->snapshots_table();
        $sql = "SELECT * FROM {$table} WHERE event_id = %d AND source = %s ORDER BY snapshot_at_utc DESC, id DESC LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, $eventId, $source), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function get_lines_for_snapshot(int $snapshotId): array
    {
        if ($snapshotId <= 0) {
            return [];
        }

        global $wpdb;
        $table = $this->lines_table();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE snapshot_id = %d ORDER BY id ASC", $snapshotId),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function delete_selftest_snapshots_for_event(int $eventId): void
    {
        if ($eventId <= 0) {
            return;
        }

        global $wpdb;
        $snapshots = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$this->snapshots_table()} WHERE event_id = %d AND note = %s",
                $eventId,
                'selftest'
            ),
            ARRAY_A
        );

        if (!is_array($snapshots) || $snapshots === []) {
            return;
        }

        foreach ($snapshots as $row) {
            $snapshotId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($snapshotId <= 0) {
                continue;
            }
            $wpdb->delete($this->lines_table(), ['snapshot_id' => $snapshotId], ['%d']);
            $wpdb->delete($this->snapshots_table(), ['id' => $snapshotId], ['%d']);
        }
    }

    /** @return array<int, array{product_id:int,qty_units:int}> */
    public function get_aggregated_forecast(string $fromUtc, string $toUtc): array
    {
        if ($fromUtc === '' || $toUtc === '') {
            return [];
        }

        global $wpdb;
        $snapshots = $this->snapshots_table();
        $lines = $this->lines_table();
        $sql = "SELECT l.product_id, SUM(l.qty_units) AS qty_units
                FROM {$lines} l
                INNER JOIN {$snapshots} s ON s.id = l.snapshot_id
                WHERE s.snapshot_at_utc >= %s AND s.snapshot_at_utc <= %s
                GROUP BY l.product_id";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $fromUtc, $toUtc), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $qty = isset($row['qty_units']) ? (int) $row['qty_units'] : 0;
            if ($productId <= 0 || $qty < 0) {
                continue;
            }
            $out[] = [
                'product_id' => $productId,
                'qty_units' => $qty,
            ];
        }

        return $out;
    }

    private function snapshots_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . Schema::TABLE_SNAPSHOTS;
    }

    private function lines_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . Schema::TABLE_LINES;
    }

    private function update_snapshot(int $snapshotId, string $snapshotAtUtc, ?int $createdBy, ?string $note): void
    {
        if ($snapshotId <= 0) {
            return;
        }

        global $wpdb;
        $payload = [
            'snapshot_at_utc' => $snapshotAtUtc,
            'created_at_utc' => current_time('mysql', true),
            'created_by' => $createdBy !== null ? (int) $createdBy : null,
            'note' => $note !== null ? (string) $note : null,
        ];

        $wpdb->update($this->snapshots_table(), $payload, ['id' => $snapshotId], [
            '%s',
            '%s',
            '%d',
            '%s',
        ], ['%d']);
    }

    public function update_snapshot_by_id(int $snapshotId, string $snapshotAtUtc, ?int $createdBy, ?string $note): void
    {
        $this->update_snapshot($snapshotId, $snapshotAtUtc, $createdBy, $note);
    }
}
