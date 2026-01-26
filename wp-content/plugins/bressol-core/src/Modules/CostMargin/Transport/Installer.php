<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Transport;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public const VERSION = '1.0.0';
    private const VERSION_OPTION = 'bressol_transport_schema_version';

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

        $tables = [];
        $tables[] = Schema::snapshots_table_sql($wpdb->prefix . 'bressol_transport_snapshots', $charsetCollate);
        $tables[] = Schema::allocations_table_sql($wpdb->prefix . 'bressol_transport_allocations', $charsetCollate);

        foreach ($tables as $tableSql) {
            dbDelta($tableSql);
        }

        update_option(self::VERSION_OPTION, self::VERSION, false);
    }
}
