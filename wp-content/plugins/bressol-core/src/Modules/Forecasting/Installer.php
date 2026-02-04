<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public static function maybe_upgrade(): void
    {
        $installed = (string) get_option(Schema::VERSION_OPTION, '');
        if ($installed === '' || version_compare($installed, Schema::SCHEMA_VERSION, '<')) {
            (new self())->install();
        }
    }

    public function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $tables = [];
        $tables[] = Schema::snapshots_table_sql($wpdb->prefix . Schema::TABLE_SNAPSHOTS, $charsetCollate);
        $tables[] = Schema::snapshot_lines_table_sql($wpdb->prefix . Schema::TABLE_LINES, $charsetCollate);

        foreach ($tables as $tableSql) {
            dbDelta($tableSql);
        }

        update_option(Schema::VERSION_OPTION, Schema::SCHEMA_VERSION, false);
    }
}
