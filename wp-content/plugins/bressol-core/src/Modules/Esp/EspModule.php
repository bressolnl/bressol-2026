<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp;

use Bressol\Core\ModuleInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class EspModule implements ModuleInterface
{
    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'registerAdminMenu']);
    }

    public function registerAdminMenu(): void
    {
        $capability = 'manage_options';

        add_menu_page(
            'Bressol ESP',
            'Bressol ESP',
            $capability,
            'bressol-esp',
            [AdminPages::class, 'renderOverview'],
            'dashicons-email-alt2',
            56
        );

        add_submenu_page(
            'bressol-esp',
            'Campañas',
            'Campañas',
            $capability,
            'bressol-esp-campaigns',
            [AdminPages::class, 'renderCampaigns']
        );

        add_submenu_page(
            'bressol-esp',
            'Plantillas',
            'Plantillas',
            $capability,
            'bressol-esp-templates',
            [AdminPages::class, 'renderTemplates']
        );

        add_submenu_page(
            'bressol-esp',
            'Segmentos',
            'Segmentos',
            $capability,
            'bressol-esp-segments',
            [AdminPages::class, 'renderSegments']
        );

        add_submenu_page(
            'bressol-esp',
            'Emails manuales',
            'Emails manuales',
            $capability,
            'bressol-esp-manual',
            [AdminPages::class, 'renderManualEmails']
        );

        add_submenu_page(
            'bressol-esp',
            'Métricas',
            'Métricas',
            $capability,
            'bressol-esp-metrics',
            [AdminPages::class, 'renderMetrics']
        );

        add_submenu_page(
            'bressol-esp',
            'Consentimientos',
            'Consentimientos',
            $capability,
            'bressol-esp-consents',
            [AdminPages::class, 'renderConsents']
        );

        add_submenu_page(
            'bressol-esp',
            'Exportaciones',
            'Exportaciones',
            $capability,
            'bressol-esp-exports',
            [AdminPages::class, 'renderExports']
        );

        add_submenu_page(
            'bressol-esp',
            'Configuración SMTP',
            'Configuración SMTP',
            $capability,
            'bressol-esp-settings',
            [AdminPages::class, 'renderSettings']
        );

        add_submenu_page(
            'bressol-esp',
            'Auditoría',
            'Auditoría',
            $capability,
            'bressol-esp-audit',
            [AdminPages::class, 'renderAudit']
        );
    }
}
