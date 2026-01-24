<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents;

if (!defined('ABSPATH')) {
    exit;
}

final class Schema
{
    public static function events_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(190) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'event',
            status VARCHAR(20) NOT NULL DEFAULT 'planned',
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
            location_name VARCHAR(190) NOT NULL,
            address TEXT NOT NULL,
            google_maps_url VARCHAR(255) NULL,
            distance_km DECIMAL(6,2) NULL,
            travel_time_min INT UNSIGNED NULL,
            booth_fee_cents INT UNSIGNED NOT NULL DEFAULT 0,
            other_costs_cents INT UNSIGNED NOT NULL DEFAULT 0,
            expected_sales_cents INT UNSIGNED NULL,
            notes LONGTEXT NULL,
            channels VARCHAR(10) NOT NULL DEFAULT 'both',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY type (type),
            KEY start_at (start_at),
            KEY channels (channels)
        ) {$charsetCollate};";
    }
}
