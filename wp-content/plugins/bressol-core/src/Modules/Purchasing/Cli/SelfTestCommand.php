<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Cli;

use Bressol\Modules\Purchasing\Services\Capabilities;
use Bressol\Modules\Purchasing\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class SelfTestCommand
{
    private Capabilities $capabilities;
    private Settings $settings;

    public function __construct(Capabilities $capabilities, Settings $settings)
    {
        $this->capabilities = $capabilities;
        $this->settings = $settings;
    }

    /**
     * Selftest del scaffold de Purchasing.
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        \WP_CLI::log('OK scaffold purchasing');
        \WP_CLI::log('Caps: base=' . $this->capabilities->get_base_capability() . ' sensitive=' . $this->capabilities->get_sensitive_capability());

        $settings = $this->settings->get();
        \WP_CLI::log('Settings: purchasing_enabled=' . ($settings['purchasing_enabled'] ? 'true' : 'false'));
        \WP_CLI::log('Settings: purchasing_cron_enabled=' . ($settings['purchasing_cron_enabled'] ? 'true' : 'false'));
        \WP_CLI::log('Settings: purchase_planning_enabled=' . ($settings['purchase_planning_enabled'] ? 'true' : 'false'));

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_suppliers';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        \WP_CLI::log('Table suppliers: ' . ($exists ? 'ok' : 'missing'));

        $poTable = $wpdb->prefix . 'bressol_purchase_orders';
        $poLinesTable = $wpdb->prefix . 'bressol_purchase_order_lines';
        $receivingsTable = $wpdb->prefix . 'bressol_receivings';
        $receivingLinesTable = $wpdb->prefix . 'bressol_receiving_lines';
        $poExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $poTable));
        $poLinesExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $poLinesTable));
        $receivingsExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $receivingsTable));
        $receivingLinesExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $receivingLinesTable));
        \WP_CLI::log('Table purchase_orders: ' . ($poExists ? 'ok' : 'missing'));
        \WP_CLI::log('Table purchase_order_lines: ' . ($poLinesExists ? 'ok' : 'missing'));
        \WP_CLI::log('Table receivings: ' . ($receivingsExists ? 'ok' : 'missing'));
        \WP_CLI::log('Table receiving_lines: ' . ($receivingLinesExists ? 'ok' : 'missing'));

        $roles = function_exists('wp_roles') ? wp_roles() : null;
        $role = $roles ? $roles->get_role('administrator') : null;
        $hasCap = $role ? $role->has_cap($this->capabilities->get_sensitive_capability()) : false;
        \WP_CLI::log('Cap exists: ' . ($hasCap ? 'true' : 'false'));

        if ($exists) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            \WP_CLI::log('Suppliers count: ' . $count);
        }

        if ($poExists) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$poTable}");
            \WP_CLI::log('Purchase orders count: ' . $count);
        }
        if ($poLinesExists) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$poLinesTable}");
            \WP_CLI::log('Purchase order lines count: ' . $count);
        }
        if ($receivingsExists) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$receivingsTable}");
            \WP_CLI::log('Receivings count: ' . $count);
        }
        if ($receivingLinesExists) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$receivingLinesTable}");
            \WP_CLI::log('Receiving lines count: ' . $count);
        }

        if ($poExists && $poLinesExists && $receivingsExists && $receivingLinesExists) {
            $samplePoId = (int) $wpdb->get_var("SELECT id FROM {$poTable} ORDER BY id DESC LIMIT 1");
            if ($samplePoId > 0) {
                $orderedRows = $wpdb->get_results(
                    $wpdb->prepare("SELECT id, qty FROM {$poLinesTable} WHERE po_id = %d", $samplePoId),
                    ARRAY_A
                );
                $receivedRows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT l.po_line_id, SUM(l.qty_received) AS qty_received
                         FROM {$receivingLinesTable} l
                         INNER JOIN {$receivingsTable} r ON r.id = l.receiving_id
                         WHERE r.po_id = %d
                         GROUP BY l.po_line_id",
                        $samplePoId
                    ),
                    ARRAY_A
                );
                $receivedMap = [];
                foreach ($receivedRows as $row) {
                    $receivedMap[(int) $row['po_line_id']] = (int) $row['qty_received'];
                }
                $ok = true;
                foreach ($orderedRows as $row) {
                    $lineId = (int) ($row['id'] ?? 0);
                    $ordered = (int) ($row['qty'] ?? 0);
                    $received = $receivedMap[$lineId] ?? 0;
                    if ($received > $ordered) {
                        $ok = false;
                        break;
                    }
                }
                \WP_CLI::log('Receiving integrity: ' . ($ok ? 'ok' : 'check'));
            }
        }
    }
}
