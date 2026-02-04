<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private const PAGE_DIAGNOSTICS = 'bressol-forecasting';

    public function registerMenus(): void
    {
        $capability = $this->get_capability();

        add_submenu_page(
            'bressol',
            'Forecasting',
            'Forecasting',
            $capability,
            self::PAGE_DIAGNOSTICS,
            [$this, 'renderDiagnosticsPage']
        );
    }

    public function renderDiagnosticsPage(): void
    {
        (new DiagnosticsPage())->render();
    }

    private function get_capability(): string
    {
        return class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
    }
}
