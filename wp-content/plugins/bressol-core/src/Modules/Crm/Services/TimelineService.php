<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class TimelineService
{
    /**
     * @return array<int, array<string, string|int|float>>
     */
    public function get_timeline(int $customerId, int $limit = 50): array
    {
        $limit = max(1, $limit);

        $events = array_merge(
            $this->list_audit_logs($customerId, $limit),
            $this->list_points_ledger($customerId, $limit),
            $this->list_redemptions($customerId, $limit),
            $this->list_order_sync($customerId, $limit)
        );

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) $b['date'], (string) $a['date']);
        });

        return array_slice($events, 0, $limit);
    }

    /** @return array<int, array<string, string|int|float>> */
    public function list_audit_logs(int $customerId, int $limit): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_audit_logs';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT action, created_at FROM {$table} WHERE entity_type = %s AND entity_id = %d ORDER BY created_at DESC LIMIT %d",
                'customer',
                $customerId,
                $limit
            )
        );

        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'date' => (string) $row->created_at,
                'type' => 'audit',
                'summary' => (string) $row->action,
            ];
        }

        return $events;
    }

    /** @return array<int, array<string, string|int|float>> */
    public function list_points_ledger(int $customerId, int $limit): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_ledger';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT points, source_type, source_id, earned_at FROM {$table} WHERE customer_id = %d ORDER BY earned_at DESC LIMIT %d",
                $customerId,
                $limit
            )
        );

        $events = [];
        foreach ($rows as $row) {
            $summary = sprintf(
                '%s (%d pts) #%s',
                (string) $row->source_type,
                (int) $row->points,
                $row->source_id !== null ? (string) $row->source_id : '-'
            );

            $events[] = [
                'date' => (string) $row->earned_at,
                'type' => 'points',
                'summary' => $summary,
            ];
        }

        return $events;
    }

    /** @return array<int, array<string, string|int|float>> */
    public function list_redemptions(int $customerId, int $limit): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_redemptions';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT redemption_type, points_used, id, created_at FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC LIMIT %d",
                $customerId,
                $limit
            )
        );

        $events = [];
        foreach ($rows as $row) {
            $summary = sprintf(
                '%s (%d pts) #%d',
                (string) $row->redemption_type,
                (int) $row->points_used,
                (int) $row->id
            );

            $events[] = [
                'date' => (string) $row->created_at,
                'type' => 'redemption',
                'summary' => $summary,
            ];
        }

        return $events;
    }

    /** @return array<int, array<string, string|int|float>> */
    public function list_order_sync(int $customerId, int $limit): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_order_sync';
        if (!$this->table_exists($table)) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT order_id, order_total, processed_at FROM {$table} WHERE customer_id = %d ORDER BY processed_at DESC LIMIT %d",
                $customerId,
                $limit
            )
        );

        $events = [];
        foreach ($rows as $row) {
            $summary = sprintf(
                'order #%d (total %s)',
                (int) $row->order_id,
                (string) $row->order_total
            );

            $events[] = [
                'date' => (string) $row->processed_at,
                'type' => 'order',
                'summary' => $summary,
            ];
        }

        return $events;
    }

    private function table_exists(string $table): bool
    {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }
}
