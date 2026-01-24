<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class OpenedItemsRepository
{
    private \wpdb $db;
    private string $itemsTable;
    private string $eventsTable;

    public function __construct(?\wpdb $db = null)
    {
        global $wpdb;
        $this->db = $db ?? $wpdb;
        $this->itemsTable = $this->db->prefix . 'bressol_opened_items';
        $this->eventsTable = $this->db->prefix . 'bressol_opened_item_events';
    }

    /** @param array<string, mixed> $data */
    public function insert_opened_item(array $data): int
    {
        $result = $this->db->insert($this->itemsTable, $data, $this->build_item_formats($data));
        if ($result === false) {
            return 0;
        }

        return (int) $this->db->insert_id;
    }

    /** @return array<int, array<string, mixed>> */
    public function list_open_items(int $limit, int $offset): array
    {
        $sql = "SELECT * FROM {$this->itemsTable} WHERE status = %s ORDER BY opened_at DESC LIMIT %d OFFSET %d";
        $prepared = $this->db->prepare($sql, 'open', $limit, $offset);
        $results = $this->db->get_results($prepared, ARRAY_A);
        return is_array($results) ? $results : [];
    }

    /** @param int[] $ids */
    public function discard_items(array $ids, string $reason, string $discardedAt): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "UPDATE {$this->itemsTable}
            SET status = %s, discarded_at = %s, discard_reason = %s
            WHERE status = %s AND id IN ({$placeholders})";
        $params = array_merge(['discarded', $discardedAt, $reason, 'open'], $ids);
        $prepared = $this->db->prepare($sql, $params);

        return (int) $this->db->query($prepared);
    }

    /** @param array<string, mixed> $data */
    private function build_item_formats(array $data): array
    {
        $formats = [
            'product_id' => '%d',
            'opened_at' => '%s',
            'opened_event_id' => '%d',
            'opened_by_user_id' => '%d',
            'initial_qty' => '%d',
            'internal_order_id' => '%d',
            'status' => '%s',
            'discarded_at' => '%s',
            'discard_reason' => '%s',
        ];

        return array_intersect_key($formats, $data);
    }
}
