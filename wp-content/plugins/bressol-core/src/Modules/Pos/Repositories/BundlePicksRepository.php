<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class BundlePicksRepository
{
    private \wpdb $db;
    private string $table;

    public function __construct(?\wpdb $db = null)
    {
        global $wpdb;
        $this->db = $db ?? $wpdb;
        $this->table = $this->db->prefix . 'bressol_pos_bundle_picks';
    }

    /** @param array<string, mixed> $data */
    public function insert_pick(array $data): bool
    {
        $formats = $this->build_formats($data);
        if ($formats === []) {
            return false;
        }

        $columns = array_keys($formats);
        $placeholders = array_values($formats);
        $values = [];
        foreach ($columns as $column) {
            $values[] = $data[$column];
        }

        $sql = "INSERT IGNORE INTO {$this->table} (" . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';
        $prepared = $this->db->prepare($sql, $values);
        if ($prepared === null) {
            return false;
        }

        $result = $this->db->query($prepared);
        return $result !== false && $this->db->rows_affected > 0;
    }

    /** @param array<string, mixed> $data */
    private function build_formats(array $data): array
    {
        $formats = [
            'sale_id' => '%d',
            'parent_line_key' => '%s',
            'picked_product_id' => '%d',
            'picked_sku' => '%s',
            'qty' => '%d',
            'event_id' => '%d',
            'created_at' => '%s',
        ];

        return array_intersect_key($formats, $data);
    }
}
