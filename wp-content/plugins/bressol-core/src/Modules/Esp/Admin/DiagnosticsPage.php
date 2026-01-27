<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Admin;

use Bressol\Modules\Esp\Module;
use Bressol\Modules\Esp\Services\Capabilities;

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
        $table = $wpdb->prefix . 'bressol_esp_campaigns';
        $tableExists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        ) === $table;
        $nextCron = wp_next_scheduled(Module::CRON_HOOK);
        $nextCronText = $nextCron ? gmdate('Y-m-d H:i:s', (int) $nextCron) . ' UTC' : 'No programado';
        $version = (string) get_option('bressol_esp_version', '');

        echo '<div class="wrap">';
        echo '<h1>Diagnóstico ESP</h1>';
        echo '<table class="widefat striped" style="max-width:700px;">';
        echo '<tbody>';
        echo '<tr><th>Capability</th><td>' . esc_html(Capabilities::CAP) . '</td></tr>';
        echo '<tr><th>current_user_can</th><td>' . esc_html(current_user_can(Capabilities::CAP) ? 'Sí' : 'No') . '</td></tr>';
        echo '<tr><th>Slug actual</th><td>' . esc_html($page) . '</td></tr>';
        echo '<tr><th>Tabla principal</th><td>' . esc_html($table) . '</td></tr>';
        echo '<tr><th>Tabla existe</th><td>' . esc_html($tableExists ? 'Sí' : 'No') . '</td></tr>';
        echo '<tr><th>Versión instalada</th><td>' . esc_html($version !== '' ? $version : '(vacío)') . '</td></tr>';
        echo '<tr><th>Próximo cron</th><td>' . esc_html($nextCronText) . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';
    }
}
