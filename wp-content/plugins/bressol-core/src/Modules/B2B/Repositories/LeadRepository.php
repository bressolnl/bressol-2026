<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class LeadRepository
{
    private const TABLE_NAME = 'bressol_b2b_leads';

    /** @var string[] */
    private const COLUMNS = [
        'created_at',
        'updated_at',
        'email',
        'email_lower',
        'company_name',
        'contact_name',
        'city',
        'phone',
        'business_type',
        'tier',
        'status',
        'contact_basis',
        'source',
        'source_ref_event_id',
        'interests_json',
        'owner_user_id',
        'lead_score',
        'last_activity_at',
        'consent_token',
        'consent_token_created_at',
        'consent_token_expires_at',
        'consented_at',
        'reminder_sent_at',
    ];

    /** @var array<string, string> */
    private const FORMATS = [
        'created_at' => '%s',
        'updated_at' => '%s',
        'email' => '%s',
        'email_lower' => '%s',
        'company_name' => '%s',
        'contact_name' => '%s',
        'city' => '%s',
        'phone' => '%s',
        'business_type' => '%s',
        'tier' => '%s',
        'status' => '%s',
        'contact_basis' => '%s',
        'source' => '%s',
        'source_ref_event_id' => '%d',
        'interests_json' => '%s',
        'owner_user_id' => '%d',
        'lead_score' => '%d',
        'last_activity_at' => '%s',
        'consent_token' => '%s',
        'consent_token_created_at' => '%s',
        'consent_token_expires_at' => '%s',
        'consented_at' => '%s',
        'reminder_sent_at' => '%s',
    ];

    public function find_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function find_by_email_lower(string $emailLower): ?array
    {
        if ($emailLower === '') {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE email_lower = %s", $emailLower),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function find_by_token(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE consent_token = %s", $token),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        global $wpdb;
        $payload = $this->filter_payload($data);
        if ($payload === []) {
            return 0;
        }

        $payload = $this->normalize_payload($payload);
        $formats = $this->build_formats($payload);
        $inserted = $wpdb->insert($this->table(), $payload, $formats);
        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        $payload = $this->filter_payload($data);
        if ($payload === []) {
            return false;
        }

        $payload = $this->normalize_payload($payload);
        $formats = $this->build_formats($payload);
        $updated = $wpdb->update($this->table(), $payload, ['id' => $id], $formats, ['%d']);

        return $updated !== false;
    }

    public function increment_score(int $leadId, int $points): void
    {
        if ($leadId <= 0 || $points === 0) {
            return;
        }

        global $wpdb;
        $table = $this->table();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET lead_score = GREATEST(0, lead_score + %d) WHERE id = %d",
                $points,
                $leadId
            )
        );
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

        $sql = "SELECT * FROM {$table} {$whereSql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
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

    /** @return array<int, array<string, mixed>> */
    public function find_for_reminder(string $cutoff, int $limit = 50): array
    {
        if ($cutoff === '') {
            return [];
        }

        global $wpdb;
        $table = $this->table();
        $eventsTable = $wpdb->prefix . 'bressol_b2b_lead_events';
        $limit = max(1, $limit);

        $sql = "SELECT l.* FROM {$table} l
            WHERE l.status = %s
              AND l.consented_at IS NOT NULL
              AND l.consented_at <= %s
              AND l.reminder_sent_at IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM {$eventsTable} e
                WHERE e.lead_id = l.id
                  AND e.type IN (%s, %s)
              )
            ORDER BY l.consented_at ASC
            LIMIT %d";

        $prepared = $wpdb->prepare($sql, 'CONSENTED', $cutoff, 'catalog_clicked', 'pricelist_clicked', $limit);
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function filter_payload(array $data): array
    {
        $payload = [];
        foreach (self::COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $payload[$column] = $data[$column];
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload
     *  @return array<int, string>
     */
    private function build_formats(array $payload): array
    {
        $formats = [];
        foreach ($payload as $key => $value) {
            $formats[] = self::FORMATS[$key] ?? '%s';
        }
        return $formats;
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function normalize_payload(array $payload): array
    {
        $nullable = [
            'company_name',
            'contact_name',
            'city',
            'phone',
            'business_type',
            'source_ref_event_id',
            'interests_json',
            'last_activity_at',
            'consented_at',
            'reminder_sent_at',
        ];

        foreach ($nullable as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = $payload[$key];
            if ($value === '' || $value === null) {
                $payload[$key] = null;
                continue;
            }
            if ($key === 'source_ref_event_id') {
                $payload[$key] = is_numeric($value) ? (int) $value : null;
            }
        }

        return $payload;
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
        if (!empty($filters['tier'])) {
            $clauses[] = 'tier = %s';
            $params[] = sanitize_key((string) $filters['tier']);
        }
        if (!empty($filters['source'])) {
            $clauses[] = 'source = %s';
            $params[] = sanitize_key((string) $filters['source']);
        }
        if (!empty($filters['owner_user_id'])) {
            $clauses[] = 'owner_user_id = %d';
            $params[] = (int) $filters['owner_user_id'];
        }

        $whereSql = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$whereSql, $params];
    }
}
