<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing;

if (!defined('ABSPATH')) {
    exit;
}

final class Schema
{
    public const SCHEMA_VERSION = '0.1.0';
    public const VERSION_OPTION = 'bressol_purchasing_schema_version';

    public static function suppliers_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            supplier_code VARCHAR(64) NOT NULL,
            name VARCHAR(255) NOT NULL,
            lead_time_days INT NOT NULL DEFAULT 0,
            min_order_cents BIGINT NULL,
            notes TEXT NULL,
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY supplier_code (supplier_code)
        ) {$charsetCollate};";
    }

    public static function purchase_orders_table_sql(string $table, string $charsetCollate): string
    {
        // Note: UNIQUE on nullable po_number allows multiple NULLs in MySQL.
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            po_number VARCHAR(64) NULL,
            supplier_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            ordered_at_utc DATETIME NULL,
            expected_at_utc DATETIME NULL,
            received_at_utc DATETIME NULL,
            customs_fees_cents BIGINT NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            tax_rate_bp INT NULL,
            warehouse_code VARCHAR(40) NOT NULL DEFAULT 'ALICANTE',
            created_at_utc DATETIME NOT NULL,
            updated_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY po_number (po_number),
            KEY supplier_id (supplier_id),
            KEY status (status)
        ) {$charsetCollate};";
    }

    public static function purchase_order_lines_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            po_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NULL,
            sku VARCHAR(64) NULL,
            qty INT NOT NULL,
            unit_cost_excl_tax_cents BIGINT NOT NULL,
            line_total_excl_tax_cents BIGINT NOT NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY po_id (po_id),
            KEY product_id (product_id),
            KEY sku (sku)
        ) {$charsetCollate};";
    }

    public static function receivings_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            po_id BIGINT UNSIGNED NOT NULL,
            received_at_utc DATETIME NOT NULL,
            note VARCHAR(255) NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY po_id (po_id),
            KEY received_at_utc (received_at_utc)
        ) {$charsetCollate};";
    }

    public static function receiving_lines_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            receiving_id BIGINT UNSIGNED NOT NULL,
            po_line_id BIGINT UNSIGNED NULL,
            qty_received INT NOT NULL,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY receiving_id (receiving_id),
            KEY po_line_id (po_line_id)
        ) {$charsetCollate};";
    }
}
