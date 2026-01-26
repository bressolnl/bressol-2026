<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Transport;

if (!defined('ABSPATH')) {
    exit;
}

final class Schema
{
    public static function snapshots_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            transfer_id BIGINT UNSIGNED NOT NULL,
            total_cost_cents INT NOT NULL,
            method VARCHAR(12) NOT NULL DEFAULT 'weight',
            status VARCHAR(10) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            closed_at DATETIME NULL,
            closed_by BIGINT UNSIGNED NULL,
            note TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY transfer_id (transfer_id),
            KEY status (status)
        ) {$charsetCollate};";
    }

    public static function allocations_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            snapshot_id BIGINT UNSIGNED NOT NULL,
            transfer_line_id BIGINT UNSIGNED NOT NULL,
            lot_id_nl BIGINT UNSIGNED NOT NULL,
            qty_units INT NOT NULL,
            weight_total_grams INT NOT NULL,
            allocated_cost_cents INT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY snapshot_id (snapshot_id),
            KEY lot_id_nl (lot_id_nl)
        ) {$charsetCollate};";
    }
}
