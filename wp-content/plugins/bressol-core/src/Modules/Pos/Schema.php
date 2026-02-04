<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos;

if (!defined('ABSPATH')) {
    exit;
}

final class Schema
{
    public static function opened_items_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            opened_at DATETIME NOT NULL,
            opened_event_id BIGINT UNSIGNED NOT NULL,
            opened_by_user_id BIGINT UNSIGNED NOT NULL,
            initial_qty INT NOT NULL,
            internal_order_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            discarded_at DATETIME NULL,
            discard_reason VARCHAR(255) NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY product_id (product_id),
            KEY opened_event_id (opened_event_id),
            KEY internal_order_id (internal_order_id)
        ) {$charsetCollate};";
    }

    public static function opened_item_events_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            opened_item_id BIGINT UNSIGNED NOT NULL,
            event_id BIGINT UNSIGNED NOT NULL,
            used_at DATETIME NOT NULL,
            note VARCHAR(255) NULL,
            PRIMARY KEY (id),
            KEY opened_item_id (opened_item_id),
            KEY event_id (event_id),
            KEY used_at (used_at)
        ) {$charsetCollate};";
    }

    public static function bundle_picks_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sale_id BIGINT UNSIGNED NOT NULL,
            parent_line_key VARCHAR(64) NOT NULL,
            picked_product_id BIGINT UNSIGNED NOT NULL,
            picked_sku VARCHAR(64) NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY sale_line_sku (sale_id, parent_line_key, picked_sku),
            KEY sale_id (sale_id),
            KEY parent_line_key (parent_line_key),
            KEY event_id (event_id)
        ) {$charsetCollate};";
    }
}
