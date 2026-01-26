<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Admin;

use Bressol\Modules\Purchasing\Services\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private Capabilities $capabilities;

    public function __construct(Capabilities $capabilities)
    {
        $this->capabilities = $capabilities;
    }

    public function registerMenus(): void
    {
        $capability = $this->capabilities->get_base_capability();

        add_submenu_page(
            'bressol',
            'Purchasing',
            'Purchasing',
            $capability,
            'bressol-purchasing',
            [$this, 'renderSuppliersPage']
        );

        add_submenu_page(
            'bressol-purchasing',
            'Suppliers',
            'Suppliers',
            $capability,
            'bressol-purchasing',
            [$this, 'renderSuppliersPage']
        );

        add_submenu_page(
            'bressol-purchasing',
            'Purchase Orders',
            'Purchase Orders',
            $capability,
            'bressol-purchasing-pos',
            [$this, 'renderPurchaseOrdersPage']
        );

        add_submenu_page(
            'bressol-purchasing',
            'Receivings',
            'Receivings',
            $capability,
            'bressol-purchasing-receivings',
            [$this, 'renderReceivingsPage']
        );
    }

    public function renderSuppliersPage(): void
    {
        $this->assert_can_manage();

        echo '<div class="wrap">';
        echo '<h1>Purchasing - Suppliers</h1>';
        $this->render_notice();

        echo '<p class="description">Listado placeholder. Sin datos todavía.</p>';
        $this->render_add_button('bressol_purchasing_add_supplier', 'bressol_purchasing_add_supplier_nonce');
        $this->render_empty_table('Suppliers');
        echo '</div>';
    }

    public function renderPurchaseOrdersPage(): void
    {
        $this->assert_can_manage();

        echo '<div class="wrap">';
        echo '<h1>Purchasing - Purchase Orders</h1>';
        $this->render_notice();

        echo '<p class="description">Listado placeholder. Sin órdenes todavía.</p>';
        $this->render_add_button('bressol_purchasing_add_purchase_order', 'bressol_purchasing_add_purchase_order_nonce');
        $this->render_empty_table('Purchase Orders');
        echo '</div>';
    }

    public function renderReceivingsPage(): void
    {
        $this->assert_can_manage();

        echo '<div class="wrap">';
        echo '<h1>Purchasing - Receivings</h1>';
        $this->render_notice();

        echo '<p class="description">Listado placeholder. Sin recepciones todavía.</p>';
        $this->render_add_button('bressol_purchasing_add_receiving', 'bressol_purchasing_add_receiving_nonce');
        $this->render_empty_table('Receivings');
        echo '</div>';
    }

    private function assert_can_manage(): void
    {
        if (!$this->capabilities->current_user_can_manage()) {
            wp_die('No autorizado.');
        }
    }

    private function render_notice(): void
    {
        $notice = isset($_GET['purchasing_notice']) ? sanitize_text_field(wp_unslash($_GET['purchasing_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $message = 'TODO: acción no implementada.';
        $type = 'notice-warning';
        if ($notice === 'forbidden') {
            $message = 'No autorizado.';
            $type = 'notice-error';
        } elseif ($notice === 'invalid_nonce') {
            $message = 'Nonce inválido. Intenta de nuevo.';
            $type = 'notice-error';
        }

        echo '<p class="notice ' . esc_attr($type) . '" style="padding:8px 12px;">';
        echo esc_html($message);
        echo '</p>';
    }

    private function render_add_button(string $action, string $nonceAction): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        wp_nonce_field($nonceAction);
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '" />';
        echo '<button type="submit" class="button button-primary">Add</button>';
        echo '</form>';
    }

    private function render_empty_table(string $label): void
    {
        echo '<table class="widefat striped" style="max-width:840px;">';
        echo '<thead><tr><th>' . esc_html($label) . '</th></tr></thead>';
        echo '<tbody><tr><td>No hay datos.</td></tr></tbody>';
        echo '</table>';
    }
}
