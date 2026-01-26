<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Transfers\Repositories;

use DateTimeImmutable;

if (!defined('ABSPATH')) {
    exit;
}

final class TransferLineRepository
{
    private const TABLE = 'bressol_stock_transfer_lines';

    public function add_line(
        int $transferId,
        int $productId,
        int $qtyUnits,
        $expiryDate = null,
        ?int $unitCogsCents = null,
        ?int $unitWeightOverrideGrams = null,
        ?int $lineWeightTotalGrams = null
    ): int {
        if ($transferId <= 0 || $productId <= 0 || $qtyUnits <= 0) {
            return 0;
        }

        global $wpdb;

        $payload = [
            'transfer_id' => $transferId,
            'product_id' => $productId,
            'qty_units' => $qtyUnits,
            'expiry_date' => $this->normalize_date($expiryDate),
            'unit_cogs_cents' => $unitCogsCents !== null ? (int) $unitCogsCents : null,
            'unit_weight_override_grams' => $unitWeightOverrideGrams !== null ? (int) $unitWeightOverrideGrams : null,
            'line_weight_total_grams' => $lineWeightTotalGrams !== null ? (int) $lineWeightTotalGrams : null,
            'created_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($this->table(), $payload, [
            '%d',
            '%d',
            '%d',
            '%s',
            '%d',
            '%d',
            '%d',
            '%s',
        ]);

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update_unit_cogs(int $lineId, int $unitCogsCents): bool
    {
        if ($lineId <= 0) {
            return false;
        }

        global $wpdb;

        $table = $this->table();
        $sql = "UPDATE {$table} SET unit_cogs_cents = %d WHERE id = %d AND unit_cogs_cents IS NULL";
        $updated = $wpdb->query($wpdb->prepare($sql, $unitCogsCents, $lineId));

        return $updated === 1;
    }

    /** @return array<int, array<string, mixed>> */
    public function get_lines(int $transferId): array
    {
        if ($transferId <= 0) {
            return [];
        }

        global $wpdb;
        $table = $this->table();
        $sql = "SELECT * FROM {$table} WHERE transfer_id = %d ORDER BY id ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $transferId), ARRAY_A);

        return is_array($rows) ? $rows : [];
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
