<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Admin;

use Bressol\Modules\Esp\Module;
use Bressol\Modules\Esp\Services\Capabilities;
use Bressol\Modules\Esp\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class DiagnosticsPage
{
    public static function render(): void
    {
        if (!current_user_can(Capabilities::CAP)) {
            wp_die('No autorizado.');
        }

        global $wpdb;
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $tables = [
            'campaigns' => $wpdb->prefix . 'bressol_esp_campaigns',
            'lists' => $wpdb->prefix . 'bressol_esp_lists',
            'list_members' => $wpdb->prefix . 'bressol_esp_list_members',
            'jobs' => $wpdb->prefix . 'bressol_esp_jobs',
            'queue_items' => $wpdb->prefix . 'bressol_esp_queue_items',
            'unsubscribes' => $wpdb->prefix . 'bressol_esp_unsubscribes',
            'audit_logs' => $wpdb->prefix . 'bressol_esp_audit_logs',
        ];
        $tableStatus = [];
        foreach ($tables as $key => $tableName) {
            $tableStatus[$key] = $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $tableName)
            ) === $tableName;
        }
        $nextCron = wp_next_scheduled(Module::CRON_HOOK);
        $nextCronText = $nextCron ? gmdate('Y-m-d H:i:s', (int) $nextCron) . ' UTC' : 'No programado';
        $version = (string) get_option('bressol_esp_version', '');
        $settings = (new Settings())->get_settings();

        echo '<div class="wrap">';
        echo '<h1>Diagnóstico ESP</h1>';
        echo '<table class="widefat striped" style="max-width:700px;">';
        echo '<tbody>';
        echo '<tr><th>Capability</th><td>' . esc_html(Capabilities::CAP) . '</td></tr>';
        echo '<tr><th>current_user_can</th><td>' . esc_html(current_user_can(Capabilities::CAP) ? 'Sí' : 'No') . '</td></tr>';
        echo '<tr><th>Slug actual</th><td>' . esc_html($page) . '</td></tr>';
        foreach ($tables as $key => $tableName) {
            $label = str_replace('_', ' ', $key);
            echo '<tr><th>Tabla ' . esc_html($label) . '</th><td>' . esc_html($tableName) . '</td></tr>';
            echo '<tr><th>Tabla ' . esc_html($label) . ' existe</th><td>' . esc_html($tableStatus[$key] ? 'Sí' : 'No') . '</td></tr>';
        }
        echo '<tr><th>Versión instalada</th><td>' . esc_html($version !== '' ? $version : '(vacío)') . '</td></tr>';
        echo '<tr><th>Próximo cron</th><td>' . esc_html($nextCronText) . '</td></tr>';
        echo '<tr><th>Enabled</th><td>' . esc_html($settings['enabled'] ? 'Sí' : 'No') . '</td></tr>';
        echo '<tr><th>Daily cap</th><td>' . esc_html((string) $settings['daily_cap']) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';
    }
}
