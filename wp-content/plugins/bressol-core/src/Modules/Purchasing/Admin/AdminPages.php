<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Admin;

use Bressol\Modules\Purchasing\Services\Capabilities;
use Bressol\Modules\Purchasing\Repositories\SupplierRepository;

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
        $capability = $this->capabilities->get_sensitive_capability();

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

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : '';
        if ($view === 'edit') {
            $supplierId = isset($_GET['supplier_id']) ? absint($_GET['supplier_id']) : 0;
            $this->renderSupplierForm($supplierId);
            return;
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $repository = new SupplierRepository();
        $rows = $repository->list_suppliers(50, 0, $search);

        echo '<div class="wrap">';
        echo '<h1>Purchasing - Suppliers</h1>';
        $this->render_notice();
        $this->render_add_supplier_link();
        $this->render_search_form($search);
        $this->render_suppliers_table($rows);
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
        if (!$this->capabilities->current_user_can_sensitive()) {
            wp_die('No autorizado.');
        }
    }

    private function render_notice(): void
    {
        $notice = isset($_GET['purchasing_notice']) ? sanitize_text_field(wp_unslash($_GET['purchasing_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $message = 'Acción completada.';
        $type = 'notice-success';
        if ($notice === 'forbidden') {
            $message = 'No autorizado.';
            $type = 'notice-error';
        } elseif ($notice === 'invalid_nonce') {
            $message = 'Nonce inválido. Intenta de nuevo.';
            $type = 'notice-error';
        } elseif ($notice === 'supplier_saved') {
            $message = 'Proveedor guardado.';
        } elseif ($notice === 'supplier_save_failed') {
            $message = 'No se pudo guardar el proveedor.';
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

    private function render_add_supplier_link(): void
    {
        $url = add_query_arg([
            'page' => 'bressol-purchasing',
            'view' => 'edit',
        ], admin_url('admin.php'));
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Add supplier</a></p>';
    }

    private function render_search_form(string $search): void
    {
        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="bressol-purchasing" />';
        echo '<input type="text" name="s" value="' . esc_attr($search) . '" placeholder="Buscar proveedor" />';
        echo '<button class="button">Buscar</button>';
        echo '</form>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function render_suppliers_table(array $rows): void
    {
        echo '<table class="widefat striped" style="max-width:980px;">';
        echo '<thead><tr>';
        echo '<th>Code</th><th>Name</th><th>Lead time (days)</th><th>MOQ (€)</th><th>Updated</th>';
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="5">No hay proveedores.</td></tr>';
            echo '</tbody></table>';
            return;
        }

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $code = (string) ($row['supplier_code'] ?? '');
            $name = (string) ($row['name'] ?? '');
            $lead = (int) ($row['lead_time_days'] ?? 0);
            $moq = $row['min_order_cents'];
            $updated = (string) ($row['updated_at_utc'] ?? '');

            $editUrl = add_query_arg([
                'page' => 'bressol-purchasing',
                'view' => 'edit',
                'supplier_id' => $id,
            ], admin_url('admin.php'));
            $label = $name !== '' ? $name : ('Supplier #' . $id);
            $moqLabel = $moq === null ? '-' : $this->format_euros((int) $moq);

            echo '<tr>';
            echo '<td>' . esc_html($code) . '</td>';
            echo '<td><a href="' . esc_url($editUrl) . '">' . esc_html($label) . '</a></td>';
            echo '<td>' . esc_html((string) $lead) . '</td>';
            echo '<td>' . esc_html($moqLabel) . '</td>';
            echo '<td>' . esc_html($updated) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderSupplierForm(int $supplierId): void
    {
        $repository = new SupplierRepository();
        $supplier = $supplierId > 0 ? $repository->get_supplier($supplierId) : null;

        if ($supplierId > 0 && !$supplier) {
            $supplierId = 0;
        }

        $code = $supplier ? (string) $supplier['supplier_code'] : '';
        $name = $supplier ? (string) $supplier['name'] : '';
        $lead = $supplier ? (int) $supplier['lead_time_days'] : 0;
        $minOrder = $supplier ? $supplier['min_order_cents'] : null;
        $notes = $supplier ? (string) $supplier['notes'] : '';

        echo '<div class="wrap">';
        echo '<h1>' . ($supplierId > 0 ? 'Editar proveedor' : 'Nuevo proveedor') . '</h1>';
        $this->render_notice();

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bressol_purchasing_save_supplier');
        echo '<input type="hidden" name="action" value="bressol_purchasing_save_supplier" />';
        if ($supplierId > 0) {
            echo '<input type="hidden" name="supplier_id" value="' . esc_attr((string) $supplierId) . '" />';
        }
        echo '<table class="form-table">';
        echo '<tr><th>Code</th><td><input type="text" name="supplier_code" value="' . esc_attr($code) . '" required /></td></tr>';
        echo '<tr><th>Name</th><td><input type="text" name="name" value="' . esc_attr($name) . '" required /></td></tr>';
        echo '<tr><th>Lead time (days)</th><td><input type="number" min="0" max="365" name="lead_time_days" value="' . esc_attr((string) $lead) . '" /></td></tr>';
        echo '<tr><th>MOQ (cents)</th><td><input type="number" min="0" name="min_order_cents" value="' . esc_attr($minOrder === null ? '' : (string) $minOrder) . '" /></td></tr>';
        echo '<tr><th>Notes</th><td><textarea name="notes" rows="4" cols="50">' . esc_textarea($notes) . '</textarea>';
        echo '<p class="description">Notas internas sin PII.</p>';
        echo '</td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">Guardar</button></p>';
        echo '</form>';

        $backUrl = add_query_arg(['page' => 'bressol-purchasing'], admin_url('admin.php'));
        echo '<p><a href="' . esc_url($backUrl) . '">&larr; Volver al listado</a></p>';
        echo '</div>';
    }

    private function format_euros(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
