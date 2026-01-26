<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class SupplierRepository
{
    /** @return array<int, array<string, mixed>> */
    public function list_suppliers(int $limit = 50, int $offset = 0, string $search = ''): array
    {
        global $wpdb;

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $table = $wpdb->prefix . 'bressol_suppliers';
        $whereSql = '';
        $params = [];

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $whereSql = 'WHERE supplier_code LIKE %s OR name LIKE %s';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT id, supplier_code, name, lead_time_days, min_order_cents, notes, created_at_utc, updated_at_utc
            FROM {$table}
            {$whereSql}
            ORDER BY updated_at_utc DESC
            LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $prepared = $params !== [] ? $wpdb->prepare($sql, $params) : $wpdb->prepare($sql, $limit, $offset);

        $rows = $wpdb->get_results($prepared, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function get_supplier(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $table = $wpdb->prefix . 'bressol_suppliers';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, supplier_code, name, lead_time_days, min_order_cents, notes, created_at_utc, updated_at_utc
                 FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function get_supplier_by_code(string $code): ?array
    {
        global $wpdb;

        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $table = $wpdb->prefix . 'bressol_suppliers';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, supplier_code, name, lead_time_days, min_order_cents, notes, created_at_utc, updated_at_utc
                 FROM {$table} WHERE supplier_code = %s",
                $code
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function insert_supplier(array $data): int
    {
        global $wpdb;

        $data = $this->filter_supplier_data($data);
        if ($data === []) {
            return 0;
        }

        $table = $wpdb->prefix . 'bressol_suppliers';
        $formats = $this->formats_for_supplier_data($data);
        $result = $wpdb->insert($table, $data, $formats);

        if ($result === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /** @param array<string, mixed> $data */
    public function update_supplier(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $data = $this->filter_supplier_data($data);
        if ($data === []) {
            return false;
        }

        $table = $wpdb->prefix . 'bressol_suppliers';
        $formats = $this->formats_for_supplier_data($data);
        $result = $wpdb->update($table, $data, ['id' => $id], $formats, ['%d']);

        return $result !== false;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function filter_supplier_data(array $data): array
    {
        $allowed = [
            'supplier_code',
            'name',
            'lead_time_days',
            'min_order_cents',
            'notes',
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
    private function formats_for_supplier_data(array $data): array
    {
        $formats = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $formats[] = '%s';
                continue;
            }
            if (in_array($key, ['lead_time_days', 'min_order_cents'], true)) {
                $formats[] = '%d';
                continue;
            }
            if (in_array($key, ['created_at_utc', 'updated_at_utc'], true)) {
                $formats[] = '%s';
                continue;
            }
            $formats[] = '%s';
        }

        return $formats;
    }
}
