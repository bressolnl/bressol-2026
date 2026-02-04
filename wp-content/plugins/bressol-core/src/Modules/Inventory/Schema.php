<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory;

if (!defined('ABSPATH')) {
    exit;
}

final class Schema
{
    public static function stock_lots_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            location VARCHAR(2) NOT NULL,
            qty_on_hand INT NOT NULL DEFAULT 0,
            expiry_date DATE NULL,
            unit_cogs_cents INT NOT NULL DEFAULT 0,
            unit_weight_grams INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'manual',
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY location_product (location, product_id),
            KEY expiry_date (expiry_date)
        ) {$charsetCollate};";
    }

    public static function lot_moves_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lot_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(12) NOT NULL,
            qty INT NOT NULL,
            ref_type VARCHAR(12) NOT NULL,
            ref_id VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            note TEXT NULL,
            PRIMARY KEY (id),
            KEY lot_id (lot_id),
            KEY ref (ref_type, ref_id)
        ) {$charsetCollate};";
    }

    public static function stock_transfers_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            status VARCHAR(10) NOT NULL DEFAULT 'draft',
            shipped_at DATETIME NULL,
            received_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            note TEXT NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) {$charsetCollate};";
    }

    public static function stock_transfer_lines_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            transfer_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            qty_units INT NOT NULL,
            expiry_date DATE NULL,
            unit_cogs_cents INT NULL,
            unit_weight_override_grams INT NULL,
            line_weight_total_grams INT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY transfer_id (transfer_id),
            KEY product_id (product_id)
        ) {$charsetCollate};";
    }

    public static function transfer_lot_allocations_table_sql(string $table, string $charsetCollate): string
    {
        // Nota: dbDelta puede no aplicar UNIQUE en algunos setups; ver checklist manual.
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            transfer_id BIGINT UNSIGNED NOT NULL,
            transfer_line_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            lot_id_es BIGINT UNSIGNED NOT NULL,
            lot_id_nl BIGINT UNSIGNED NULL,
            qty_units INT NOT NULL,
            unit_cogs_cents INT NOT NULL,
            expiry_date DATE NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_alloc (transfer_id, transfer_line_id, lot_id_es, qty_units, unit_cogs_cents),
            KEY transfer_id (transfer_id),
            KEY transfer_line_id (transfer_line_id),
            KEY lot_id_es (lot_id_es),
            KEY lot_id_nl (lot_id_nl)
        ) {$charsetCollate};";
    }
}
