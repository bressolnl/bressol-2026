<?php
/**
 * Plugin Name: Bressol Core
 * Plugin URI: https://bressol.nl
 * Description: Lógica de negocio Bressol (packs, upsells, CRM, funnels, tracking dataLayer, integraciones).
 * Version: 0.1.0
 * Author: Bressol
 * Text Domain: bressol-core
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', function () {
    // Placeholder inicial: confirmamos que el plugin carga correctamente.
});

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="notice notice-success"><p><strong>Bressol Core</strong> activo correctamente.</p></div>';
});