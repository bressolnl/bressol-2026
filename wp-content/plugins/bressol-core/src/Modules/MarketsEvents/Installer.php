<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public const VERSION = '1.0.0';
    private const VERSION_OPTION = 'bressol_markets_events_schema_version';

    public static function maybe_upgrade(): void
    {
        $installed = (string) get_option(self::VERSION_OPTION, '');
        if ($installed === '' || version_compare($installed, self::VERSION, '<')) {
            (new self())->install();
        }
    }

    public function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'bressol_events';

        $tables = [];
        $tables[] = Schema::events_table_sql($table, $charsetCollate);

        foreach ($tables as $tableSql) {
            dbDelta($tableSql);
        }

        update_option(self::VERSION_OPTION, self::VERSION, false);
    }
}
