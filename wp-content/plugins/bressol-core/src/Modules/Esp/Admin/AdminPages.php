<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Admin;

use Bressol\Modules\Esp\Services\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    public function registerMenus(): void
    {
        $capability = Capabilities::CAP;

        $this->ensure_parent_menu_exists();

        add_submenu_page(
            'bressol',
            'ESP',
            'ESP',
            $capability,
            'bressol_esp',
            [$this, 'renderHome']
        );

        add_submenu_page(
            'bressol',
            'ESP - Configuración',
            'Configuración',
            $capability,
            'bressol_esp_settings',
            [$this, 'renderSettings']
        );

        add_submenu_page(
            'bressol',
            'Diagnóstico ESP',
            'Diagnóstico',
            $capability,
            'bressol_esp_diagnostics',
            [DiagnosticsPage::class, 'render']
        );
    }

    public function renderHome(): void
    {
        $this->ensureAccess();
        echo '<div class="wrap"><h1>ESP</h1><p>Página placeholder del módulo ESP.</p></div>';
    }

    public function renderSettings(): void
    {
        $this->ensureAccess();
        echo '<div class="wrap"><h1>Configuración ESP</h1><p>Página placeholder de configuración.</p></div>';
    }

    private function ensureAccess(): void
    {
        if (!current_user_can(Capabilities::CAP)) {
            wp_die('No autorizado.');
        }
    }

    private function ensure_parent_menu_exists(): void
    {
        global $menu;
        $slug = 'bressol';

        foreach ((array) $menu as $item) {
            if (is_array($item) && isset($item[2]) && (string) $item[2] === $slug) {
                return;
            }
        }

        add_menu_page(
            'Bressol',
            'Bressol',
            'manage_options',
            $slug,
            static function (): void {
                echo '<div class="wrap"><h1>Bressol</h1></div>';
            },
            'dashicons-store',
            55
        );
    }
}
