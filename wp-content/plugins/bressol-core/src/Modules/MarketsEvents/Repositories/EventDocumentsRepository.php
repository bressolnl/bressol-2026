<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class EventDocumentsRepository
{
    private const TABLE = 'bressol_event_documents';

    /** @return array<int, array<string, mixed>> */
    public function list_by_event(int $eventId): array
    {
        if ($eventId <= 0) {
            return [];
        }

        global $wpdb;
        $table = $this->table();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE event_id = %d ORDER BY created_at DESC", $eventId),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function insert_document(
        int $eventId,
        int $attachmentId,
        string $title,
        string $mimeType,
        string $fileUrl
    ): int {
        if ($eventId <= 0 || $attachmentId <= 0 || $fileUrl === '') {
            return 0;
        }

        global $wpdb;
        $inserted = $wpdb->insert($this->table(), [
            'event_id' => $eventId,
            'attachment_id' => $attachmentId,
            'title' => $title !== '' ? $title : ('Document #' . $attachmentId),
            'mime_type' => $mimeType,
            'file_url' => $fileUrl,
            'created_at' => current_time('mysql'),
        ], ['%d', '%d', '%s', '%s', '%s', '%s']);

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
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
