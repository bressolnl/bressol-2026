<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class ReceivingRepository
{
    /** @param array<string, mixed> $filters
     *  @return array<int, array<string, mixed>>
     */
    public function list_receivings(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        global $wpdb;

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $table = $wpdb->prefix . 'bressol_receivings';
        $linesTable = $wpdb->prefix . 'bressol_receiving_lines';
        $poTable = $wpdb->prefix . 'bressol_purchase_orders';
        $supplierTable = $wpdb->prefix . 'bressol_suppliers';

        $whereParts = [];
        $params = [];

        $poId = isset($filters['po_id']) ? (int) $filters['po_id'] : 0;
        if ($poId > 0) {
            $whereParts[] = 'r.po_id = %d';
            $params[] = $poId;
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $whereParts[] = 'p.po_number LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $dateFrom = isset($filters['date_from']) ? (string) $filters['date_from'] : '';
        if ($dateFrom !== '') {
            $whereParts[] = 'r.received_at_utc >= %s';
            $params[] = $dateFrom;
        }
        $dateTo = isset($filters['date_to']) ? (string) $filters['date_to'] : '';
        if ($dateTo !== '') {
            $whereParts[] = 'r.received_at_utc <= %s';
            $params[] = $dateTo;
        }

        $whereSql = $whereParts !== [] ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

        $sql = "SELECT r.id, r.po_id, r.received_at_utc, r.note, r.created_at_utc,
                p.po_number, p.supplier_id,
                s.supplier_code, s.name AS supplier_name,
                COUNT(l.id) AS lines_count
            FROM {$table} r
            INNER JOIN {$poTable} p ON p.id = r.po_id
            LEFT JOIN {$supplierTable} s ON s.id = p.supplier_id
            LEFT JOIN {$linesTable} l ON l.receiving_id = r.id
            {$whereSql}
            GROUP BY r.id
            ORDER BY r.received_at_utc DESC
            LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function get_receiving(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = $wpdb->prefix . 'bressol_receivings';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, po_id, received_at_utc, note, created_at_utc
                 FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function get_receiving_lines(int $receivingId): array
    {
        global $wpdb;

        if ($receivingId <= 0) {
            return [];
        }

        $table = $wpdb->prefix . 'bressol_receiving_lines';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, receiving_id, po_line_id, qty_received, created_at_utc
                 FROM {$table} WHERE receiving_id = %d ORDER BY id ASC",
                $receivingId
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $data */
    public function insert_receiving(array $data): int
    {
        global $wpdb;

        $data = $this->filter_receiving_data($data);
        if ($data === []) {
            return 0;
        }

        $table = $wpdb->prefix . 'bressol_receivings';
        $result = $wpdb->insert($table, $data, $this->formats_for_receiving_data($data));
        if ($result === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function insert_receiving_lines(int $receivingId, array $lines): bool
    {
        global $wpdb;

        if ($receivingId <= 0) {
            return false;
        }

        $table = $wpdb->prefix . 'bressol_receiving_lines';
        foreach ($lines as $line) {
            $data = $this->filter_receiving_line_data($line);
            if ($data === []) {
                continue;
            }
            $data['receiving_id'] = $receivingId;
            $result = $wpdb->insert($table, $data, $this->formats_for_receiving_line_data($data));
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    public function count_receivings_for_po(int $poId): int
    {
        global $wpdb;

        if ($poId <= 0) {
            return 0;
        }

        $table = $wpdb->prefix . 'bressol_receivings';
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE po_id = %d",
                $poId
            )
        );

        return (int) $count;
    }

    /** @return array<int, int> */
    public function get_received_totals_for_po(int $poId): array
    {
        global $wpdb;

        if ($poId <= 0) {
            return [];
        }

        $receivingTable = $wpdb->prefix . 'bressol_receivings';
        $linesTable = $wpdb->prefix . 'bressol_receiving_lines';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.po_line_id, SUM(l.qty_received) AS qty_received
                 FROM {$linesTable} l
                 INNER JOIN {$receivingTable} r ON r.id = l.receiving_id
                 WHERE r.po_id = %d
                 GROUP BY l.po_line_id",
                $poId
            ),
            ARRAY_A
        );

        $totals = [];
        if (!is_array($rows)) {
            return $totals;
        }

        foreach ($rows as $row) {
            $lineId = (int) ($row['po_line_id'] ?? 0);
            $qty = (int) ($row['qty_received'] ?? 0);
            if ($lineId > 0) {
                $totals[$lineId] = $qty;
            }
        }

        return $totals;
    }

    public function delete_receiving(int $receivingId): bool
    {
        global $wpdb;

        if ($receivingId <= 0) {
            return false;
        }

        $linesTable = $wpdb->prefix . 'bressol_receiving_lines';
        $receivingTable = $wpdb->prefix . 'bressol_receivings';

        $wpdb->delete($linesTable, ['receiving_id' => $receivingId], ['%d']);
        $deleted = $wpdb->delete($receivingTable, ['id' => $receivingId], ['%d']);

        return $deleted !== false;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function filter_receiving_data(array $data): array
    {
        $allowed = [
            'po_id',
            'received_at_utc',
            'note',
            'created_at_utc',
        ];

        $filtered = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $filtered[$key] = $data[$key];
            }
        }

        return $filtered;
    }

    /** @param array<string, mixed> $data
     *  @return array<int, string>
     */
    private function formats_for_receiving_data(array $data): array
    {
        $formats = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $formats[] = '%s';
                continue;
            }
            if ($key === 'po_id') {
                $formats[] = '%d';
                continue;
            }
            $formats[] = '%s';
        }

        return $formats;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function filter_receiving_line_data(array $data): array
    {
        $allowed = [
            'receiving_id',
            'po_line_id',
            'qty_received',
            'created_at_utc',
        ];

        $filtered = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $filtered[$key] = $data[$key];
            }
        }

        return $filtered;
    }

    /** @param array<string, mixed> $data
     *  @return array<int, string>
     */
    private function formats_for_receiving_line_data(array $data): array
    {
        $formats = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $formats[] = '%s';
                continue;
            }
            if (in_array($key, ['receiving_id', 'po_line_id', 'qty_received'], true)) {
                $formats[] = '%d';
                continue;
            }
            $formats[] = '%s';
        }

        return $formats;
    }
}
