<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Admin;

use Bressol\Modules\Inventory\Services\SellableService;
use Bressol\Modules\Inventory\Support\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private Capabilities $capabilities;
    private SellableService $sellableService;

    public function __construct(Capabilities $capabilities, SellableService $sellableService)
    {
        $this->capabilities = $capabilities;
        $this->sellableService = $sellableService;
    }

    public function registerMenus(): void
    {
        $capability = $this->capabilities->get_base_capability();

        add_submenu_page(
            'bressol',
            'Inventory',
            'Inventory',
            $capability,
            'bressol-inventory',
            [$this, 'renderInventoryPage']
        );

        add_submenu_page(
            'bressol-inventory',
            'Lots',
            'Lots',
            'manage_options',
            'bressol-lots',
            [$this, 'renderLotsPage']
        );

        add_submenu_page(
            'bressol-inventory',
            'Transfers',
            'Transfers',
            'manage_options',
            'bressol-transfers',
            [$this, 'renderTransfersPage']
        );

        add_submenu_page(
            'bressol-inventory',
            'Expiry Alerts',
            'Expiry Alerts',
            'manage_options',
            'bressol-expiry-alerts',
            [$this, 'renderExpiryAlertsPage']
        );
    }

    public function renderInventoryPage(): void
    {
        if (!current_user_can($this->capabilities->get_base_capability())) {
            wp_die('No autorizado.');
        }

        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $packs = $this->sellableService->list_packs($query, 50);

        echo '<div class="wrap">';
        echo '<h1>Inventory Overview</h1>';
        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="bressol-inventory" />';
        echo '<input type="text" name="q" value="' . esc_attr($query) . '" placeholder="Buscar por ID o título" style="min-width:240px;" />';
        echo '<button class="button">Buscar</button>';
        echo '</form>';

        if ($packs === []) {
            echo '<p>No hay packs para mostrar.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>Pack</th>';
        echo '<th>Sellable</th>';
        echo '<th>Cuello de botella</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($packs as $pack) {
            $packId = (int) ($pack['product_id'] ?? 0);
            $packName = (string) ($pack['product_name'] ?? '');
            $sellable = (int) ($pack['sellable'] ?? 0);
            $bottleneckId = (int) ($pack['bottleneck_product_id'] ?? 0);
            $bottleneckName = (string) ($pack['bottleneck_product_name'] ?? '');
            $sellableLabel = $sellable >= 1000000 ? '∞' : (string) $sellable;

            $link = $packId > 0 ? admin_url('post.php?post=' . $packId . '&action=edit') : '';
            $label = $packName !== '' ? $packName : ('Pack #' . $packId);
            $bottleneckLabel = $bottleneckId > 0 ? ($bottleneckName !== '' ? $bottleneckName : ('Product #' . $bottleneckId)) : '-';

            echo '<tr>';
            if ($link !== '') {
                echo '<td><a href="' . esc_url($link) . '">' . esc_html($label) . '</a></td>';
            } else {
                echo '<td>' . esc_html($label) . '</td>';
            }
            echo '<td>' . esc_html($sellableLabel) . '</td>';
            echo '<td>' . esc_html($bottleneckLabel) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function renderLotsPage(): void
    {
        (new LotsPage())->render();
    }

    public function renderTransfersPage(): void
    {
        (new TransfersPage())->render();
    }

    public function renderExpiryAlertsPage(): void
    {
        (new ExpiryAlertsPage())->render();
    }
}
