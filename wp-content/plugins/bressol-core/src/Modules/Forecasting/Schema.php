<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting;

if (!defined('ABSPATH')) {
    exit;
}

final class Schema
{
    public const SCHEMA_VERSION = '0.1.0';
    public const VERSION_OPTION = 'bressol_forecasting_schema_version';
    public const TABLE_SNAPSHOTS = 'bressol_forecast_snapshots';
    public const TABLE_LINES = 'bressol_forecast_snapshot_lines';

    public static function snapshots_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'manual',
            status VARCHAR(12) NOT NULL DEFAULT 'final',
            snapshot_at_utc DATETIME NOT NULL,
            created_at_utc DATETIME NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            note VARCHAR(200) NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY created_at_utc (created_at_utc),
            KEY snapshot_at_utc (snapshot_at_utc),
            KEY source (source)
        ) {$charsetCollate};";
    }

    public static function snapshot_lines_table_sql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            snapshot_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            qty_units INT NOT NULL DEFAULT 0,
            created_at_utc DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY snapshot_product (snapshot_id, product_id),
            KEY snapshot_id (snapshot_id),
            KEY product_id (product_id)
        ) {$charsetCollate};";
    }
}
