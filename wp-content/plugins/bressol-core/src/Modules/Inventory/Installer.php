<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public const VERSION = '1.2.0';
    private const VERSION_OPTION = 'bressol_inventory_schema_version';

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
        $tables[] = Schema::stock_lots_table_sql($wpdb->prefix . 'bressol_stock_lots', $charsetCollate);
        $tables[] = Schema::lot_moves_table_sql($wpdb->prefix . 'bressol_lot_moves', $charsetCollate);
        $tables[] = Schema::stock_transfers_table_sql($wpdb->prefix . 'bressol_stock_transfers', $charsetCollate);
        $tables[] = Schema::stock_transfer_lines_table_sql($wpdb->prefix . 'bressol_stock_transfer_lines', $charsetCollate);
        $tables[] = Schema::transfer_lot_allocations_table_sql($wpdb->prefix . 'bressol_transfer_lot_allocations', $charsetCollate);

        foreach ($tables as $tableSql) {
            dbDelta($tableSql);
        }

        update_option(self::VERSION_OPTION, self::VERSION, false);
    }
}
