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

    /** @return array<int, array<string, mixed>> */
    public function list_open_items_for_event(int $eventId, int $limit, int $offset): array
    {
        $sql = "SELECT items.*, CASE WHEN events.id IS NULL THEN 0 ELSE 1 END AS used_today
            FROM {$this->itemsTable} AS items
            LEFT JOIN {$this->eventsTable} AS events
                ON events.opened_item_id = items.id AND events.event_id = %d AND events.note = %s
            WHERE items.status = %s
            ORDER BY items.opened_at DESC
            LIMIT %d OFFSET %d";
        $prepared = $this->db->prepare($sql, $eventId, 'used', 'open', $limit, $offset);
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
    public function insert_opened_item_event(array $data): int
    {
        /**
         * QA manual:
         * 1) Abrir sampling desde POS (crear pedido interno) y anotar opened_item_id.
         * 2) Repetir la misma request para el mismo opened_item_id/event_id/note.
         * 3) Verificar que en opened_item_events solo existe 1 fila.
         * 4) Marcar como desechado y comprobar que se inserta note='discarded'.
         */
        $openedItemId = isset($data['opened_item_id']) ? (int) $data['opened_item_id'] : 0;
        $eventId = isset($data['event_id']) ? (int) $data['event_id'] : 0;
        $usedAt = isset($data['used_at']) ? (string) $data['used_at'] : '';
        $note = isset($data['note']) ? (string) $data['note'] : '';
        $note = substr($note, 0, 50);
        if ($note === '') {
            $note = '';
        }

        $sql = "INSERT INTO {$this->eventsTable}
            (opened_item_id, event_id, used_at, note)
            VALUES (%d, %d, %s, %s)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)";
        $prepared = $this->db->prepare($sql, $openedItemId, $eventId, $usedAt, $note);
        $result = $this->db->query($prepared);
        if ($result === false) {
            return 0;
        }

        return (int) $this->db->insert_id;
    }

    public function has_opened_item_event(int $openedItemId, int $eventId, string $note): bool
    {
        $note = substr($note, 0, 50);
        $sql = "SELECT id FROM {$this->eventsTable} WHERE opened_item_id = %d AND event_id = %d AND note = %s LIMIT 1";
        $prepared = $this->db->prepare($sql, $openedItemId, $eventId, $note);
        $value = $this->db->get_var($prepared);

        return !empty($value);
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

    /** @param array<string, mixed> $data */
    private function build_event_formats(array $data): array
    {
        $formats = [
            'opened_item_id' => '%d',
            'event_id' => '%d',
            'used_at' => '%s',
            'note' => '%s',
        ];

        return array_intersect_key($formats, $data);
    }
}
