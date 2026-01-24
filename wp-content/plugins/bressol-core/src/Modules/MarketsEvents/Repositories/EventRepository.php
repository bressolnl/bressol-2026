<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class EventRepository
{
    private const TABLE_NAME = 'bressol_events';

    /** @var string[] */
    private const COLUMNS = [
        'title',
        'type',
        'status',
        'start_at',
        'end_at',
        'timezone',
        'location_name',
        'address',
        'google_maps_url',
        'distance_km',
        'travel_time_min',
        'booth_fee_cents',
        'other_costs_cents',
        'expected_sales_cents',
        'notes',
        'channels',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    private const FORMATS = [
        'title' => '%s',
        'type' => '%s',
        'status' => '%s',
        'start_at' => '%s',
        'end_at' => '%s',
        'timezone' => '%s',
        'location_name' => '%s',
        'address' => '%s',
        'google_maps_url' => '%s',
        'distance_km' => '%s',
        'travel_time_min' => '%d',
        'booth_fee_cents' => '%d',
        'other_costs_cents' => '%d',
        'expected_sales_cents' => '%d',
        'notes' => '%s',
        'channels' => '%s',
        'created_at' => '%s',
        'updated_at' => '%s',
    ];

    public function find_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $this->table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        global $wpdb;
        $table = $this->table();

        $payload = $this->normalize_payload($this->filter_payload($data));
        if ($payload === []) {
            return 0;
        }

        $formats = $this->build_formats($payload);
        $inserted = $wpdb->insert($table, $payload, $formats);

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
        $table = $this->table();

        $payload = $this->normalize_payload($this->filter_payload($data));
        if ($payload === []) {
            return false;
        }

        $formats = $this->build_formats($payload);
        $updated = $wpdb->update($table, $payload, ['id' => $id], $formats, ['%d']);

        return $updated !== false;
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        $table = $this->table();

        $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
        return $deleted !== false;
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

        $sql = "SELECT * FROM {$table} {$whereSql} ORDER BY start_at ASC LIMIT %d OFFSET %d";
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
    public function list_upcoming_confirmed(int $limit, ?\DateTimeImmutable $from = null): array
    {
        $from = $from ?? new \DateTimeImmutable('now', wp_timezone());

        return $this->find_by_filters([
            'status' => 'confirmed',
            'date_from' => $from->format('Y-m-d H:i:s'),
        ], $limit, 1);
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
            'google_maps_url',
            'distance_km',
            'travel_time_min',
            'expected_sales_cents',
            'notes',
        ];

        foreach ($nullable as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_string($value) && trim($value) === '') {
                $payload[$key] = null;
                continue;
            }

            if ($value === '') {
                $payload[$key] = null;
                continue;
            }

            if ($key === 'distance_km') {
                if ($value === null) {
                    $payload[$key] = null;
                } elseif (is_numeric($value)) {
                    $payload[$key] = number_format((float) $value, 2, '.', '');
                } else {
                    $payload[$key] = null;
                }
                continue;
            }

            if ($key === 'travel_time_min' || $key === 'expected_sales_cents') {
                if ($value === null) {
                    $payload[$key] = null;
                } elseif (is_numeric($value)) {
                    $payload[$key] = (int) $value;
                } else {
                    $payload[$key] = null;
                }
                continue;
            }

            if ($key === 'google_maps_url' || $key === 'notes') {
                $payload[$key] = is_string($value) ? trim($value) : $value;
                if ($payload[$key] === '') {
                    $payload[$key] = null;
                }
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

        if (!empty($filters['type'])) {
            $clauses[] = 'type = %s';
            $params[] = sanitize_key((string) $filters['type']);
        }

        if (!empty($filters['channel'])) {
            $channel = sanitize_key((string) $filters['channel']);
            if ($channel === 'pos' || $channel === 'web') {
                $clauses[] = 'channels IN (%s, %s)';
                $params[] = $channel;
                $params[] = 'both';
            } elseif ($channel === 'both') {
                $clauses[] = 'channels = %s';
                $params[] = 'both';
            }
        }

        $dateFrom = $this->normalize_datetime_filter($filters['date_from'] ?? null, false);
        if ($dateFrom !== '') {
            $clauses[] = 'start_at >= %s';
            $params[] = $dateFrom;
        }

        $dateTo = $this->normalize_datetime_filter($filters['date_to'] ?? null, true);
        if ($dateTo !== '') {
            $clauses[] = 'start_at <= %s';
            $params[] = $dateTo;
        }

        $activeAt = $this->normalize_datetime_filter($filters['active_at'] ?? null, false);
        if ($activeAt !== '') {
            $clauses[] = 'start_at <= %s';
            $params[] = $activeAt;
            $clauses[] = 'end_at >= %s';
            $params[] = $activeAt;
        }

        $whereSql = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$whereSql, $params];
    }

    private function normalize_datetime_filter($value, bool $endOfDay): string
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }

        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, wp_timezone());
        if ($date instanceof \DateTimeImmutable && $date->format('Y-m-d H:i:s') === $raw) {
            return $raw;
        }

        $dateOnly = \DateTimeImmutable::createFromFormat('Y-m-d', $raw, wp_timezone());
        if ($dateOnly instanceof \DateTimeImmutable && $dateOnly->format('Y-m-d') === $raw) {
            $time = $endOfDay ? '23:59:59' : '00:00:00';
            return $dateOnly->format('Y-m-d') . ' ' . $time;
        }

        return '';
    }
}
