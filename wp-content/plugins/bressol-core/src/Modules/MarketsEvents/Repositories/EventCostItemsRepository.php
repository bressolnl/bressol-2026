<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class EventCostItemsRepository
{
    private const TABLE = 'bressol_event_cost_items';

    /** @return array<int, array<string, mixed>> */
    public function list_by_event(int $eventId): array
    {
        if ($eventId <= 0) {
            return [];
        }

        global $wpdb;
        $table = $this->table();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE event_id = %d ORDER BY incurred_at DESC, id DESC", $eventId),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @param array<int, array<string, mixed>> $items */
    public function upsert_items(int $eventId, array $items): void
    {
        if ($eventId <= 0 || $items === []) {
            return;
        }

        global $wpdb;
        $table = $this->table();

        foreach ($items as $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            $payload = [
                'event_id' => $eventId,
                'category' => (string) ($item['category'] ?? 'other'),
                'amount_cents' => (int) ($item['amount_cents'] ?? 0),
                'incurred_at' => (string) ($item['incurred_at'] ?? current_time('Y-m-d')),
                'note' => $item['note'] !== null ? (string) $item['note'] : null,
                'event_document_id' => $item['event_document_id'] !== null ? (int) $item['event_document_id'] : null,
            ];

            if ($id > 0) {
                $wpdb->update($table, $payload, ['id' => $id, 'event_id' => $eventId], [
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%d',
                ], ['%d', '%d']);
                continue;
            }

            $payload['created_at'] = current_time('mysql');
            $wpdb->insert($table, $payload, [
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
            ]);
        }
    }

    /** @param int[] $ids */
    public function delete_by_ids(int $eventId, array $ids): int
    {
        if ($eventId <= 0 || $ids === []) {
            return 0;
        }

        $ids = array_filter(array_map('intval', $ids));
        if ($ids === []) {
            return 0;
        }

        global $wpdb;
        $table = $this->table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge([$eventId], $ids);
        $sql = "DELETE FROM {$table} WHERE event_id = %d AND id IN ({$placeholders})";
        $prepared = $wpdb->prepare($sql, $params);

        $deleted = $wpdb->query($prepared);
        return is_numeric($deleted) ? (int) $deleted : 0;
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }
}
