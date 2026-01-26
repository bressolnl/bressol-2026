<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Lots\Repositories;

use DateTimeImmutable;

if (!defined('ABSPATH')) {
    exit;
}

final class LotRepository
{
    private const TABLE = 'bressol_stock_lots';

    /** @param array<string, mixed> $data */
    public function create_lot(array $data): int
    {
        global $wpdb;

        $payload = [
            'product_id' => isset($data['product_id']) ? (int) $data['product_id'] : 0,
            'location' => isset($data['location']) ? strtoupper((string) $data['location']) : '',
            'qty_on_hand' => isset($data['qty_on_hand']) ? (int) $data['qty_on_hand'] : 0,
            'expiry_date' => $this->normalize_date($data['expiry_date'] ?? null),
            'unit_cogs_cents' => isset($data['unit_cogs_cents']) ? (int) $data['unit_cogs_cents'] : 0,
            'unit_weight_grams' => isset($data['unit_weight_grams']) ? (int) $data['unit_weight_grams'] : 0,
            'created_at' => isset($data['created_at']) ? (string) $data['created_at'] : current_time('mysql'),
            'updated_at' => isset($data['updated_at']) ? (string) $data['updated_at'] : current_time('mysql'),
            'source' => isset($data['source']) ? sanitize_key((string) $data['source']) : 'manual',
        ];

        if ($payload['product_id'] <= 0 || !in_array($payload['location'], ['ES', 'NL'], true)) {
            return 0;
        }

        $inserted = $wpdb->insert($this->table(), $payload, [
            '%d',
            '%s',
            '%d',
            '%s',
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
        ]);

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /** @return array<int, array<string, mixed>> */
    public function get_lots_for_product_location(int $productId, string $location): array
    {
        if ($productId <= 0) {
            return [];
        }

        $location = strtoupper($location);
        if (!in_array($location, ['ES', 'NL'], true)) {
            return [];
        }

        global $wpdb;
        $table = $this->table();

        $sql = "SELECT * FROM {$table}
            WHERE product_id = %d AND location = %s
            ORDER BY (expiry_date IS NULL) ASC, expiry_date ASC, created_at ASC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $productId, $location), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function get_lot_by_id(int $lotId): ?array
    {
        if ($lotId <= 0) {
            return null;
        }

        global $wpdb;
        $table = $this->table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $lotId), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function increment_qty(int $lotId, int $delta): bool
    {
        if ($lotId <= 0 || $delta === 0) {
            return false;
        }

        global $wpdb;
        $table = $this->table();
        $now = current_time('mysql');

        if ($delta < 0) {
            $qty = abs($delta);
            $sql = "UPDATE {$table}
                SET qty_on_hand = qty_on_hand - %d, updated_at = %s
                WHERE id = %d AND qty_on_hand >= %d";
            $updated = $wpdb->query($wpdb->prepare($sql, $qty, $now, $lotId, $qty));
            return $updated === 1;
        }

        $sql = "UPDATE {$table}
            SET qty_on_hand = qty_on_hand + %d, updated_at = %s
            WHERE id = %d";
        $updated = $wpdb->query($wpdb->prepare($sql, $delta, $now, $lotId));
        return $updated === 1;
    }

    public function get_available_qty(
        int $productId,
        string $location,
        bool $exclude_expired = true,
        ?string $at_date = null
    ): int {
        if ($productId <= 0) {
            return 0;
        }

        $location = strtoupper($location);
        if (!in_array($location, ['ES', 'NL'], true)) {
            return 0;
        }

        $date = $this->normalize_date($at_date ?? current_time('Y-m-d'));

        global $wpdb;
        $table = $this->table();

        $where = 'product_id = %d AND location = %s';
        $params = [$productId, $location];

        if ($exclude_expired && $date !== null && $location === 'NL') {
            $where .= ' AND (expiry_date IS NULL OR expiry_date >= %s)';
            $params[] = $date;
        }

        $sql = "SELECT SUM(qty_on_hand) FROM {$table} WHERE {$where}";
        $prepared = $wpdb->prepare($sql, $params);
        $sum = $wpdb->get_var($prepared);

        if (!is_numeric($sum)) {
            return 0;
        }

        return max(0, (int) $sum);
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    private function normalize_date($value): ?string
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d');
        }

        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($date && $date->format('Y-m-d') === $raw) {
            return $raw;
        }

        return null;
    }
}
