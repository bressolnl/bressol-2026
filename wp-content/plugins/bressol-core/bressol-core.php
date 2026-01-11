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

/**
 * Autoloader simple por namespaces (sin Composer por ahora).
 * Más adelante podemos migrar a Composer si te interesa.
 */
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Bressol\\Core\\'    => __DIR__ . '/src/Core/',
        'Bressol\\Modules\\' => __DIR__ . '/src/Modules/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
});

add_action('plugins_loaded', static function (): void {
    // Arranque central del plugin.
    $plugin = new \Bressol\Core\Plugin();
    $plugin->register();
});