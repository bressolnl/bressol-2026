<?php
declare(strict_types=1);
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

register_activation_hook(__FILE__, static function (): void {
    \Bressol\Modules\Esp\Installer::install();
    \Bressol\Modules\Esp\EspModule::scheduleCron();
    \Bressol\Modules\Pos\PosModule::schedule_cron();
});

register_deactivation_hook(__FILE__, static function (): void {
    \Bressol\Modules\Esp\EspModule::clearCron();
    \Bressol\Modules\Pos\PosModule::clear_cron();
});

add_action('plugins_loaded', static function (): void {
    // Arranque central del plugin.
    $plugin = new \Bressol\Core\Plugin();
    $plugin->register();
});

register_activation_hook(__FILE__, static function (): void {
    // Instalación inicial de CRM (tablas y versión).
    (new \Bressol\Modules\Crm\Installer())->install();
    \Bressol\Modules\Crm\CrmModule::schedule_cron();
});

register_deactivation_hook(__FILE__, static function (): void {
    // Limpieza de cron CRM al desactivar el plugin.
    \Bressol\Modules\Crm\CrmModule::clear_cron();
});