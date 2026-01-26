<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Admin;

use Bressol\Modules\Purchasing\Services\Capabilities;
use Bressol\Modules\Purchasing\Domain\Enum\Status;
use Bressol\Modules\Purchasing\Repositories\PurchaseOrderRepository;
use Bressol\Modules\Purchasing\Repositories\ReceivingRepository;
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

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : '';
        if ($view === 'edit') {
            $poId = isset($_GET['po_id']) ? absint($_GET['po_id']) : 0;
            $this->renderPurchaseOrderForm($poId);
            return;
        }

        $filters = [
            'status' => isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '',
            'supplier_id' => isset($_GET['supplier_id']) ? absint($_GET['supplier_id']) : 0,
            'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        ];
        if (!in_array($filters['status'], Status::all(), true)) {
            $filters['status'] = '';
        }

        $poRepository = new PurchaseOrderRepository();
        $rows = $poRepository->list_pos(50, 0, $filters);
        $suppliers = $this->get_suppliers_index();

        echo '<div class="wrap">';
        echo '<h1>Purchasing - Purchase Orders</h1>';
        $this->render_notice();

        $this->render_add_po_link();
        $this->render_po_filters($filters, $suppliers);
        $this->render_po_table($rows, $suppliers);
        echo '</div>';
    }

    public function renderReceivingsPage(): void
    {
        $this->assert_can_manage();

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : '';
        if ($view === 'add') {
            $poId = isset($_GET['po_id']) ? absint($_GET['po_id']) : 0;
            $this->renderReceivingForm($poId);
            return;
        }

        $filters = [
            'po_id' => isset($_GET['po_id']) ? absint($_GET['po_id']) : 0,
            'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        ];
        $repository = new ReceivingRepository();
        $rows = $repository->list_receivings(50, 0, $filters);

        echo '<div class="wrap">';
        echo '<h1>Purchasing - Receivings</h1>';
        $this->render_notice();
        $this->render_receivings_filters($filters);
        $this->render_receivings_table($rows);
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
        } elseif ($notice === 'po_saved') {
            $message = 'PO guardado.';
        } elseif ($notice === 'po_save_failed') {
            $message = 'No se pudo guardar el PO.';
            $type = 'notice-error';
        } elseif ($notice === 'po_status_changed') {
            $message = 'Estado actualizado.';
        } elseif ($notice === 'po_status_failed') {
            $message = 'No se pudo actualizar el estado.';
            $type = 'notice-error';
        } elseif ($notice === 'po_locked_has_receivings') {
            $message = 'PO locked: has receivings. Lines cannot be edited.';
            $type = 'notice-warning';
        } elseif ($notice === 'receiving_saved') {
            $message = 'Recepción creada.';
        } elseif ($notice === 'receiving_save_failed') {
            $message = 'No se pudo crear la recepción.';
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

    private function render_add_po_link(): void
    {
        $url = add_query_arg([
            'page' => 'bressol-purchasing-pos',
            'view' => 'edit',
        ], admin_url('admin.php'));
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Add Purchase Order</a></p>';
    }

    /** @param array<string, mixed> $filters
     *  @param array<int, array<string, mixed>> $suppliers
     */
    private function render_po_filters(array $filters, array $suppliers): void
    {
        $status = (string) ($filters['status'] ?? '');
        $search = (string) ($filters['search'] ?? '');
        $supplierId = (int) ($filters['supplier_id'] ?? 0);

        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="bressol-purchasing-pos" />';
        echo '<input type="text" name="s" value="' . esc_attr($search) . '" placeholder="PO number" /> ';
        echo '<select name="status">';
        echo '<option value="">Todos los estados</option>';
        foreach (Status::all() as $state) {
            echo '<option value="' . esc_attr($state) . '" ' . selected($status, $state, false) . '>' . esc_html($state) . '</option>';
        }
        echo '</select> ';
        echo '<select name="supplier_id">';
        echo '<option value="0">Todos los proveedores</option>';
        foreach ($suppliers as $supplier) {
            $id = (int) ($supplier['id'] ?? 0);
            $label = $this->format_supplier_label($supplier);
            echo '<option value="' . esc_attr((string) $id) . '" ' . selected($supplierId, $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button">Filtrar</button>';
        echo '</form>';
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @param array<int, array<string, mixed>> $suppliers
     */
    private function render_po_table(array $rows, array $suppliers): void
    {
        $supplierIndex = [];
        foreach ($suppliers as $supplier) {
            if (isset($supplier['id'])) {
                $supplierIndex[(int) $supplier['id']] = $supplier;
            }
        }

        echo '<table class="widefat striped" style="max-width:1200px;">';
        echo '<thead><tr>';
        echo '<th>PO#</th><th>Supplier</th><th>Status</th><th>Lines</th><th>Total excl. tax</th><th>Customs</th><th>Updated</th>';
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="7">No hay POs.</td></tr>';
            echo '</tbody></table>';
            return;
        }

        foreach ($rows as $row) {
            $poId = (int) ($row['id'] ?? 0);
            $poNumber = (string) ($row['po_number'] ?? '');
            $supplierId = (int) ($row['supplier_id'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $linesCount = (int) ($row['lines_count'] ?? 0);
            $linesTotal = (int) ($row['lines_total_excl_tax_cents'] ?? 0);
            $customs = (int) ($row['customs_fees_cents'] ?? 0);
            $updated = (string) ($row['updated_at_utc'] ?? '');

            $editUrl = add_query_arg([
                'page' => 'bressol-purchasing-pos',
                'view' => 'edit',
                'po_id' => $poId,
            ], admin_url('admin.php'));
            $poLabel = $poNumber !== '' ? $poNumber : ('PO #' . $poId);
            $supplier = $supplierIndex[$supplierId] ?? null;
            $supplierLabel = $supplier ? $this->format_supplier_label($supplier) : ('Supplier #' . $supplierId);

            echo '<tr>';
            echo '<td><a href="' . esc_url($editUrl) . '">' . esc_html($poLabel) . '</a></td>';
            echo '<td>' . esc_html($supplierLabel) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . esc_html((string) $linesCount) . '</td>';
            echo '<td>' . esc_html($this->format_euros($linesTotal)) . '</td>';
            echo '<td>' . esc_html($this->format_euros($customs)) . '</td>';
            echo '<td>' . esc_html($updated) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderPurchaseOrderForm(int $poId): void
    {
        $poRepository = new PurchaseOrderRepository();
        $po = $poId > 0 ? $poRepository->get_po($poId) : null;
        $lines = $poId > 0 ? $poRepository->get_po_lines($poId) : [];
        $receivingRepository = new ReceivingRepository();
        $receivingCount = $poId > 0 ? $receivingRepository->count_receivings_for_po($poId) : 0;
        $receivedTotals = $poId > 0 ? $receivingRepository->get_received_totals_for_po($poId) : [];

        if ($poId > 0 && !$po) {
            $poId = 0;
        }

        $suppliers = $this->get_suppliers_index();
        $supplierId = $po ? (int) $po['supplier_id'] : 0;
        $poNumber = $po ? (string) $po['po_number'] : '';
        $customs = $po ? (int) $po['customs_fees_cents'] : 0;
        $taxRate = $po ? $po['tax_rate_bp'] : null;
        $status = $po ? (string) $po['status'] : Status::DRAFT;
        $warehouseCode = $po ? (string) $po['warehouse_code'] : 'ALICANTE';
        $isLocked = $poId > 0 && $receivingCount > 0;

        echo '<div class="wrap">';
        echo '<h1>' . ($poId > 0 ? 'Editar Purchase Order' : 'Nuevo Purchase Order') . '</h1>';
        $this->render_notice();
        if ($isLocked) {
            echo '<p class="notice notice-warning" style="padding:8px 12px;">';
            echo 'PO locked: has receivings. Lines cannot be edited.';
            echo '</p>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bressol_purchasing_save_po');
        echo '<input type="hidden" name="action" value="bressol_purchasing_save_po" />';
        if ($poId > 0) {
            echo '<input type="hidden" name="po_id" value="' . esc_attr((string) $poId) . '" />';
            echo '<input type="hidden" name="status" value="' . esc_attr($status) . '" />';
        } else {
            echo '<input type="hidden" name="status" value="' . esc_attr(Status::DRAFT) . '" />';
        }

        echo '<table class="form-table">';
        echo '<tr><th>Supplier</th><td><select name="supplier_id" required>';
        echo '<option value="0">Selecciona proveedor</option>';
        foreach ($suppliers as $supplier) {
            $id = (int) ($supplier['id'] ?? 0);
            $label = $this->format_supplier_label($supplier);
            echo '<option value="' . esc_attr((string) $id) . '" ' . selected($supplierId, $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>PO number</th><td><input type="text" name="po_number" value="' . esc_attr($poNumber) . '" /></td></tr>';
        echo '<tr><th>Customs (EUR)</th><td><input type="number" step="0.01" min="0" name="customs_fees_eur" value="' . esc_attr($this->format_euros($customs)) . '" /></td></tr>';
        echo '<tr><th>Tax rate (bp)</th><td><input type="number" min="0" max="10000" name="tax_rate_bp" value="' . esc_attr($taxRate === null ? '' : (string) $taxRate) . '" /></td></tr>';
        echo '<tr><th>Warehouse</th><td><input type="text" name="warehouse_code" value="' . esc_attr($warehouseCode) . '" /></td></tr>';
        echo '<tr><th>Set status</th><td><select name="new_status">';
        echo '<option value="">Sin cambio</option>';
        foreach (Status::all() as $state) {
            echo '<option value="' . esc_attr($state) . '">' . esc_html($state) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Estado actual: ' . esc_html($status) . '</p>';
        echo '</td></tr>';
        echo '</table>';

        echo '<h2>Líneas</h2>';
        echo '<table class="widefat striped" style="max-width:1000px;">';
        echo '<thead><tr><th>SKU</th><th>Qty</th><th>Unit cost (EUR)</th></tr></thead><tbody>';

        $rows = $lines;
        if (!$isLocked) {
            $extraRows = 3;
            for ($i = 0; $i < $extraRows; $i++) {
                $rows[] = [
                    'sku' => '',
                    'qty' => '',
                    'unit_cost_excl_tax_cents' => '',
                ];
            }
        }

        foreach ($rows as $line) {
            $sku = (string) ($line['sku'] ?? '');
            $qty = $line['qty'] ?? '';
            $unitCents = $line['unit_cost_excl_tax_cents'] ?? '';
            $unitEuros = $unitCents === '' ? '' : $this->format_euros((int) $unitCents);

            echo '<tr>';
            if ($isLocked) {
                echo '<td>' . esc_html($sku) . '</td>';
                echo '<td>' . esc_html((string) $qty) . '</td>';
                echo '<td>' . esc_html((string) $unitEuros) . '</td>';
            } else {
                echo '<td><input type="text" name="line_sku[]" value="' . esc_attr($sku) . '" /></td>';
                echo '<td><input type="number" min="1" name="line_qty[]" value="' . esc_attr((string) $qty) . '" /></td>';
                echo '<td><input type="number" step="0.01" min="0" name="line_unit_cost_eur[]" value="' . esc_attr((string) $unitEuros) . '" /></td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">Guardar</button></p>';
        echo '</form>';

        if ($poId > 0) {
            echo '<h2>Receivings</h2>';
            $this->render_po_receivings_summary($poId, $lines, $receivedTotals);
            $addUrl = add_query_arg([
                'page' => 'bressol-purchasing-receivings',
                'view' => 'add',
                'po_id' => $poId,
            ], admin_url('admin.php'));
            echo '<p><a class="button" href="' . esc_url($addUrl) . '">Add Receiving</a></p>';
        }

        $backUrl = add_query_arg(['page' => 'bressol-purchasing-pos'], admin_url('admin.php'));
        echo '<p><a href="' . esc_url($backUrl) . '">&larr; Volver al listado</a></p>';
        echo '</div>';
    }

    /** @return array<int, array<string, mixed>> */
    private function get_suppliers_index(): array
    {
        $repository = new SupplierRepository();
        return $repository->list_suppliers(200, 0, '');
    }

    /** @param array<string, mixed> $supplier */
    private function format_supplier_label(array $supplier): string
    {
        $code = (string) ($supplier['supplier_code'] ?? '');
        $name = (string) ($supplier['name'] ?? '');
        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }
        return $name !== '' ? $name : $code;
    }

    /** @param array<string, mixed> $filters */
    private function render_receivings_filters(array $filters): void
    {
        $poId = (int) ($filters['po_id'] ?? 0);
        $search = (string) ($filters['search'] ?? '');

        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="bressol-purchasing-receivings" />';
        echo '<input type="number" min="0" name="po_id" value="' . esc_attr((string) $poId) . '" placeholder="PO ID" /> ';
        echo '<input type="text" name="s" value="' . esc_attr($search) . '" placeholder="PO number" /> ';
        echo '<button class="button">Filtrar</button>';
        echo '</form>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function render_receivings_table(array $rows): void
    {
        echo '<table class="widefat striped" style="max-width:1200px;">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>PO</th><th>Supplier</th><th>Received at</th><th>Lines</th><th>Note</th><th>Created</th>';
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="7">No hay recepciones.</td></tr>';
            echo '</tbody></table>';
            return;
        }

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $poId = (int) ($row['po_id'] ?? 0);
            $poNumber = (string) ($row['po_number'] ?? '');
            $supplierCode = (string) ($row['supplier_code'] ?? '');
            $supplierName = (string) ($row['supplier_name'] ?? '');
            $receivedAt = (string) ($row['received_at_utc'] ?? '');
            $linesCount = (int) ($row['lines_count'] ?? 0);
            $note = $this->truncate_note((string) ($row['note'] ?? ''));
            $created = (string) ($row['created_at_utc'] ?? '');

            $poLabel = $poNumber !== '' ? $poNumber : ('PO #' . $poId);
            $supplierLabel = $supplierCode !== '' ? $supplierCode : ('Supplier #' . ($row['supplier_id'] ?? ''));
            if ($supplierName !== '') {
                $supplierLabel .= ' - ' . $supplierName;
            }

            echo '<tr>';
            echo '<td>' . esc_html((string) $id) . '</td>';
            echo '<td>' . esc_html($poLabel) . '</td>';
            echo '<td>' . esc_html($supplierLabel) . '</td>';
            echo '<td>' . esc_html($receivedAt) . '</td>';
            echo '<td>' . esc_html((string) $linesCount) . '</td>';
            echo '<td>' . esc_html($note) . '</td>';
            echo '<td>' . esc_html($created) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /** @param array<int, array<string, mixed>> $lines
     *  @param array<int, int> $receivedTotals
     */
    private function render_po_receivings_summary(int $poId, array $lines, array $receivedTotals): void
    {
        echo '<table class="widefat striped" style="max-width:1000px;">';
        echo '<thead><tr>';
        echo '<th>SKU</th><th>Ordered</th><th>Received</th><th>Remaining</th>';
        echo '</tr></thead><tbody>';

        if ($lines === []) {
            echo '<tr><td colspan="4">PO sin líneas.</td></tr>';
            echo '</tbody></table>';
            return;
        }

        foreach ($lines as $line) {
            $lineId = (int) ($line['id'] ?? 0);
            $sku = (string) ($line['sku'] ?? '');
            $ordered = (int) ($line['qty'] ?? 0);
            $received = isset($receivedTotals[$lineId]) ? (int) $receivedTotals[$lineId] : 0;
            $remaining = max(0, $ordered - $received);

            echo '<tr>';
            echo '<td>' . esc_html($sku) . '</td>';
            echo '<td>' . esc_html((string) $ordered) . '</td>';
            echo '<td>' . esc_html((string) $received) . '</td>';
            echo '<td>' . esc_html((string) $remaining) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderReceivingForm(int $poId): void
    {
        $poRepository = new PurchaseOrderRepository();
        $po = $poId > 0 ? $poRepository->get_po($poId) : null;
        if ($poId <= 0 || !$po) {
            echo '<div class="wrap"><p>PO inválido.</p></div>';
            return;
        }

        $lines = $poRepository->get_po_lines($poId);
        $receivingRepository = new ReceivingRepository();
        $receivedTotals = $receivingRepository->get_received_totals_for_po($poId);

        echo '<div class="wrap">';
        echo '<h1>Nuevo Receiving</h1>';
        $this->render_notice();

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bressol_purchasing_create_receiving');
        echo '<input type="hidden" name="action" value="bressol_purchasing_create_receiving" />';
        echo '<input type="hidden" name="po_id" value="' . esc_attr((string) $poId) . '" />';
        echo '<table class="form-table">';
        echo '<tr><th>Received at</th><td><input type="datetime-local" name="received_at" value="" /></td></tr>';
        echo '<tr><th>Note</th><td><input type="text" name="note" value="" maxlength="200" /></td></tr>';
        echo '</table>';

        echo '<table class="widefat striped" style="max-width:1000px;">';
        echo '<thead><tr><th>SKU</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Qty to receive</th></tr></thead><tbody>';

        foreach ($lines as $line) {
            $lineId = (int) ($line['id'] ?? 0);
            $sku = (string) ($line['sku'] ?? '');
            $ordered = (int) ($line['qty'] ?? 0);
            $received = isset($receivedTotals[$lineId]) ? (int) $receivedTotals[$lineId] : 0;
            $remaining = max(0, $ordered - $received);

            echo '<tr>';
            echo '<td>' . esc_html($sku) . '</td>';
            echo '<td>' . esc_html((string) $ordered) . '</td>';
            echo '<td>' . esc_html((string) $received) . '</td>';
            echo '<td>' . esc_html((string) $remaining) . '</td>';
            echo '<td>';
            echo '<input type="hidden" name="receiving_po_line_id[]" value="' . esc_attr((string) $lineId) . '" />';
            echo '<input type="number" min="0" max="' . esc_attr((string) $remaining) . '" name="receiving_qty[]" value="0" />';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">Crear recepción</button></p>';
        echo '</form>';

        $backUrl = add_query_arg(['page' => 'bressol-purchasing-pos', 'view' => 'edit', 'po_id' => $poId], admin_url('admin.php'));
        echo '<p><a href="' . esc_url($backUrl) . '">&larr; Volver al PO</a></p>';
        echo '</div>';
    }

    private function truncate_note(string $note): string
    {
        $note = trim($note);
        if ($note === '') {
            return '';
        }
        if (strlen($note) <= 60) {
            return $note;
        }
        return substr($note, 0, 60) . '…';
    }
}
