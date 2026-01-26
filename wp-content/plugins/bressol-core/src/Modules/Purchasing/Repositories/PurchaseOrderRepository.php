<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class PurchaseOrderRepository
{
    /** @param array<string, mixed> $filters
     *  @return array<int, array<string, mixed>>
     */
    public function list_pos(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        global $wpdb;

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $table = $wpdb->prefix . 'bressol_purchase_orders';
        $linesTable = $wpdb->prefix . 'bressol_purchase_order_lines';

        $whereParts = [];
        $params = [];

        $status = isset($filters['status']) ? (string) $filters['status'] : '';
        if ($status !== '') {
            $whereParts[] = 'p.status = %s';
            $params[] = $status;
        }

        $supplierId = isset($filters['supplier_id']) ? (int) $filters['supplier_id'] : 0;
        if ($supplierId > 0) {
            $whereParts[] = 'p.supplier_id = %d';
            $params[] = $supplierId;
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $whereParts[] = 'p.po_number LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $dateFrom = isset($filters['date_from']) ? (string) $filters['date_from'] : '';
        if ($dateFrom !== '') {
            $whereParts[] = 'p.created_at_utc >= %s';
            $params[] = $dateFrom;
        }
        $dateTo = isset($filters['date_to']) ? (string) $filters['date_to'] : '';
        if ($dateTo !== '') {
            $whereParts[] = 'p.created_at_utc <= %s';
            $params[] = $dateTo;
        }

        $whereSql = $whereParts !== [] ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

        $sql = "SELECT p.id, p.po_number, p.supplier_id, p.status, p.ordered_at_utc, p.expected_at_utc, p.received_at_utc,
                p.customs_fees_cents, p.currency, p.tax_rate_bp, p.warehouse_code, p.created_at_utc, p.updated_at_utc,
                COUNT(l.id) AS lines_count,
                COALESCE(SUM(l.line_total_excl_tax_cents), 0) AS lines_total_excl_tax_cents
            FROM {$table} p
            LEFT JOIN {$linesTable} l ON l.po_id = p.id
            {$whereSql}
            GROUP BY p.id
            ORDER BY p.updated_at_utc DESC
            LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function get_po(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = $wpdb->prefix . 'bressol_purchase_orders';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, po_number, supplier_id, status, ordered_at_utc, expected_at_utc, received_at_utc,
                        customs_fees_cents, currency, tax_rate_bp, warehouse_code, created_at_utc, updated_at_utc
                 FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function get_po_by_number(string $poNumber): ?array
    {
        global $wpdb;

        $poNumber = trim($poNumber);
        if ($poNumber === '') {
            return null;
        }

        $table = $wpdb->prefix . 'bressol_purchase_orders';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, po_number, supplier_id, status, customs_fees_cents, currency, tax_rate_bp, warehouse_code, created_at_utc, updated_at_utc
                 FROM {$table} WHERE po_number = %s",
                $poNumber
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function get_po_lines(int $poId): array
    {
        global $wpdb;

        if ($poId <= 0) {
            return [];
        }

        $table = $wpdb->prefix . 'bressol_purchase_order_lines';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, po_id, product_id, sku, qty, unit_cost_excl_tax_cents, line_total_excl_tax_cents, created_at_utc
                 FROM {$table} WHERE po_id = %d ORDER BY id ASC",
                $poId
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $data */
    public function insert_po(array $data): int
    {
        global $wpdb;

        $data = $this->filter_po_data($data);
        if ($data === []) {
            return 0;
        }

        $table = $wpdb->prefix . 'bressol_purchase_orders';
        $result = $wpdb->insert($table, $data, $this->formats_for_po_data($data));
        if ($result === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /** @param array<string, mixed> $data */
    public function update_po(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $data = $this->filter_po_data($data);
        if ($data === []) {
            return false;
        }

        $table = $wpdb->prefix . 'bressol_purchase_orders';
        $result = $wpdb->update($table, $data, ['id' => $id], $this->formats_for_po_data($data), ['%d']);
        return $result !== false;
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function replace_po_lines(int $poId, array $lines): bool
    {
        global $wpdb;

        if ($poId <= 0) {
            return false;
        }

        $linesTable = $wpdb->prefix . 'bressol_purchase_order_lines';
        $wpdb->query('START TRANSACTION');

        $deleted = $wpdb->delete($linesTable, ['po_id' => $poId], ['%d']);
        if ($deleted === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        foreach ($lines as $line) {
            $data = $this->filter_line_data($line);
            if ($data === []) {
                continue;
            }
            $data['po_id'] = $poId;
            $result = $wpdb->insert($linesTable, $data, $this->formats_for_line_data($data));
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return false;
            }
        }

        $wpdb->query('COMMIT');
        return true;
    }

    public function update_status(int $id, string $newStatus): ?string
    {
        $po = $this->get_po($id);
        if (!$po) {
            return null;
        }

        $oldStatus = (string) $po['status'];
        if ($oldStatus === $newStatus) {
            return $oldStatus;
        }

        $ok = $this->update_po($id, [
            'status' => $newStatus,
            'updated_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);

        return $ok ? $oldStatus : null;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function filter_po_data(array $data): array
    {
        $allowed = [
            'po_number',
            'supplier_id',
            'status',
            'ordered_at_utc',
            'expected_at_utc',
            'received_at_utc',
            'customs_fees_cents',
            'currency',
            'tax_rate_bp',
            'warehouse_code',
            'created_at_utc',
            'updated_at_utc',
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
    private function formats_for_po_data(array $data): array
    {
        $formats = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $formats[] = '%s';
                continue;
            }
            if (in_array($key, ['supplier_id', 'customs_fees_cents', 'tax_rate_bp'], true)) {
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
    private function filter_line_data(array $data): array
    {
        $allowed = [
            'po_id',
            'product_id',
            'sku',
            'qty',
            'unit_cost_excl_tax_cents',
            'line_total_excl_tax_cents',
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
    private function formats_for_line_data(array $data): array
    {
        $formats = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $formats[] = '%s';
                continue;
            }
            if (in_array($key, ['po_id', 'product_id', 'qty', 'unit_cost_excl_tax_cents', 'line_total_excl_tax_cents'], true)) {
                $formats[] = '%d';
                continue;
            }
            $formats[] = '%s';
        }

        return $formats;
    }
}
