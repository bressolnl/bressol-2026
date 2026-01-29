<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class TaskRepository
{
    private const TABLE_NAME = 'bressol_b2b_tasks';

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        global $wpdb;
        $payload = [
            'lead_id' => (int) ($data['lead_id'] ?? 0),
            'due_at' => (string) ($data['due_at'] ?? ''),
            'type' => (string) ($data['type'] ?? ''),
            'status' => (string) ($data['status'] ?? 'open'),
            'assigned_user_id' => (int) ($data['assigned_user_id'] ?? 0),
            'note' => isset($data['note']) ? (string) $data['note'] : null,
            'created_at' => (string) ($data['created_at'] ?? current_time('mysql')),
        ];

        if ($payload['lead_id'] <= 0 || $payload['type'] === '' || $payload['due_at'] === '') {
            return 0;
        }

        $inserted = $wpdb->insert(
            $this->table(),
            $payload,
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function mark_done(int $taskId): bool
    {
        if ($taskId <= 0) {
            return false;
        }

        global $wpdb;
        $updated = $wpdb->update(
            $this->table(),
            ['status' => 'done'],
            ['id' => $taskId],
            ['%s'],
            ['%d']
        );

        return $updated !== false;
    }

    public function find_by_id(int $taskId): ?array
    {
        if ($taskId <= 0) {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $taskId),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function has_open_task(int $leadId, string $type): bool
    {
        if ($leadId <= 0 || $type === '') {
            return false;
        }

        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table()} WHERE lead_id = %d AND type = %s AND status = %s",
                $leadId,
                $type,
                'open'
            )
        );

        return is_numeric($count) && (int) $count > 0;
    }

    public function has_recent_open_task(int $leadId, string $type, int $seconds): bool
    {
        if ($leadId <= 0 || $type === '' || $seconds <= 0) {
            return false;
        }

        $since = date('Y-m-d H:i:s', current_time('timestamp') - $seconds);
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table()}
                 WHERE lead_id = %d AND type = %s AND status = %s AND created_at >= %s",
                $leadId,
                $type,
                'open',
                $since
            )
        );

        return is_numeric($count) && (int) $count > 0;
    }

    public function has_recent_task(int $leadId, string $type, int $hours): bool
    {
        if ($leadId <= 0 || $type === '' || $hours <= 0) {
            return false;
        }

        $since = date('Y-m-d H:i:s', current_time('timestamp') - ($hours * 3600));
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

    /** @return array<int, array<string, mixed>> */
    public function list_by_lead(int $leadId): array
    {
        if ($leadId <= 0) {
            return [];
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE lead_id = %d ORDER BY status ASC, due_at ASC",
                $leadId
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $filters
     *  @return array<int, array<string, mixed>>
     */
    public function find_by_filters(array $filters, int $limit, int $page): array
    {
        global $wpdb;
        $table = $this->table();

        [$whereSql, $params] = $this->build_where($filters);
        $limit = max(1, $limit);
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        $sql = "SELECT * FROM {$table} {$whereSql} ORDER BY due_at ASC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $filters */
    public function count_by_filters(array $filters): int
    {
        global $wpdb;
        $table = $this->table();

        [$whereSql, $params] = $this->build_where($filters);
        $sql = "SELECT COUNT(*) FROM {$table} {$whereSql}";
        if ($params !== []) {
            $sql = $wpdb->prepare($sql, $params);
        }
        $count = $wpdb->get_var($sql);

        return is_numeric($count) ? (int) $count : 0;
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /** @param array<string, mixed> $filters
     *  @return array{0:string,1:array<int, mixed>}
     */
    private function build_where(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['status'])) {
            $clauses[] = 'status = %s';
            $params[] = sanitize_key((string) $filters['status']);
        }
        if (!empty($filters['assigned_user_id'])) {
            $clauses[] = 'assigned_user_id = %d';
            $params[] = (int) $filters['assigned_user_id'];
        }
        if (!empty($filters['due_from'])) {
            $clauses[] = 'due_at >= %s';
            $params[] = sanitize_text_field((string) $filters['due_from']);
        }
        if (!empty($filters['due_to'])) {
            $clauses[] = 'due_at <= %s';
            $params[] = sanitize_text_field((string) $filters['due_to']);
        }

        $whereSql = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$whereSql, $params];
    }
}
