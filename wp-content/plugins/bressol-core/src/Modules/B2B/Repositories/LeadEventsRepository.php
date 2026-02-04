<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Repositories;

use Bressol\Modules\B2B\Support\Masking;

if (!defined('ABSPATH')) {
    exit;
}

final class LeadEventsRepository
{
    private const TABLE_NAME = 'bressol_b2b_lead_events';

    public function insert_event(int $leadId, string $type, array $context = []): int
    {
        if ($leadId <= 0 || $type === '') {
            return 0;
        }

        global $wpdb;
        $context = $this->sanitize_context($context);
        $payload = [
            'lead_id' => $leadId,
            'type' => $type,
            'created_at' => current_time('mysql'),
            'context_json' => $context !== [] ? wp_json_encode($context) : null,
        ];

        $inserted = $wpdb->insert(
            $this->table(),
            $payload,
            ['%d', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /** @return array<int, array<string, mixed>> */
    public function list_by_lead(int $leadId, int $limit = 50): array
    {
        if ($leadId <= 0) {
            return [];
        }

        global $wpdb;
        $limit = max(1, $limit);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE lead_id = %d ORDER BY created_at DESC LIMIT %d",
                $leadId,
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function has_recent_event(int $leadId, string $type, int $seconds): bool
    {
        if ($leadId <= 0 || $type === '' || $seconds <= 0) {
            return false;
        }

        $since = date('Y-m-d H:i:s', current_time('timestamp') - $seconds);

        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table()} WHERE lead_id = %d AND type = %s AND created_at >= %s",
                $leadId,
                $type,
                $since
            )
        );

        return is_numeric($count) && (int) $count > 0;
    }

    public function has_recent_event_minutes(int $leadId, string $type, int $minutes): bool
    {
        $minutes = max(1, $minutes);
        return $this->has_recent_event($leadId, $type, $minutes * 60);
    }

    public function has_event(int $leadId, string $type): bool
    {
        if ($leadId <= 0 || $type === '') {
            return false;
        }

        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table()} WHERE lead_id = %d AND type = %s",
                $leadId,
                $type
            )
        );

        return is_numeric($count) && (int) $count > 0;
    }

    /** @param array<int> $leadIds
     *  @return array<int, string>
     */
    public function get_last_event_types(array $leadIds): array
    {
        $leadIds = array_values(array_unique(array_filter(array_map('intval', $leadIds))));
        if ($leadIds === []) {
            return [];
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($leadIds), '%d'));
        $table = $this->table();
        $sql = "SELECT e.lead_id, e.type
            FROM {$table} e
            INNER JOIN (
                SELECT lead_id, MAX(id) AS max_id
                FROM {$table}
                WHERE lead_id IN ({$placeholders})
                  AND type NOT IN ('catalog_view', 'pricelist_view')
                GROUP BY lead_id
            ) latest ON latest.max_id = e.id";

        $prepared = $wpdb->prepare($sql, $leadIds);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $latest = [];
        foreach ($rows as $row) {
            $leadId = (int) ($row['lead_id'] ?? 0);
            if ($leadId <= 0) {
                continue;
            }
            $latest[$leadId] = (string) ($row['type'] ?? '');
        }

        return $latest;
    }


    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    private function sanitize_context(array $context): array
    {
        if ($context === []) {
            return [];
        }
        foreach ($context as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (stripos($key, 'email') !== false) {
                $context[$key] = Masking::mask_email($value);
                continue;
            }
            if (stripos($key, 'phone') !== false) {
                unset($context[$key]);
            }
        }
        return $context;
    }

}
