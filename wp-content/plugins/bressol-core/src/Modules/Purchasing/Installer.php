<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing;

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
        $tables[] = Schema::suppliers_table_sql($wpdb->prefix . 'bressol_suppliers', $charsetCollate);
        $tables[] = Schema::purchase_orders_table_sql($wpdb->prefix . 'bressol_purchase_orders', $charsetCollate);
        $tables[] = Schema::purchase_order_lines_table_sql($wpdb->prefix . 'bressol_purchase_order_lines', $charsetCollate);
        $tables[] = Schema::receivings_table_sql($wpdb->prefix . 'bressol_receivings', $charsetCollate);
        $tables[] = Schema::receiving_lines_table_sql($wpdb->prefix . 'bressol_receiving_lines', $charsetCollate);

        foreach ($tables as $tableSql) {
            dbDelta($tableSql);
        }

        update_option(Schema::VERSION_OPTION, Schema::SCHEMA_VERSION, false);
    }
}
