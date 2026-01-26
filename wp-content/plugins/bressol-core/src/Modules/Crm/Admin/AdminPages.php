<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Admin;

use Bressol\Modules\Crm\Services\AuditLogger;
use Bressol\Modules\Crm\Services\Capabilities;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Crm\Services\ImportCustomersService;
use Bressol\Modules\Crm\Services\PointsService;
use Bressol\Modules\Crm\Services\Settings;
use Bressol\Modules\Crm\Services\TimelineService;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private Settings $settings;
    private CustomerService $customerService;
    private PointsService $pointsService;
    private AuditLogger $auditLogger;
    private ImportCustomersService $importer;
    private TimelineService $timelineService;

    public function __construct(
        Settings $settings,
        CustomerService $customerService,
        PointsService $pointsService,
        AuditLogger $auditLogger,
        ImportCustomersService $importer,
        TimelineService $timelineService
    ) {
        $this->settings = $settings;
        $this->customerService = $customerService;
        $this->pointsService = $pointsService;
        $this->auditLogger = $auditLogger;
        $this->importer = $importer;
        $this->timelineService = $timelineService;
    }

    public function registerMenus(): void
    {
        $capability = $this->get_capability();

        add_submenu_page(
            'bressol',
            'CRM',
            'CRM',
            $capability,
            'bressol_crm',
            [$this, 'renderCustomersPage']
        );

        add_submenu_page(
            'bressol_crm',
            'Clientes',
            'Clientes',
            $capability,
            'bressol_crm',
            [$this, 'renderCustomersPage']
        );

        add_submenu_page(
            'bressol_crm',
            'Ledger puntos',
            'Ledger',
            $capability,
            'bressol-crm-ledger',
            [$this, 'renderLedgerPage']
        );

        add_submenu_page(
            'bressol_crm',
            'Redenciones',
            'Redenciones',
            $capability,
            'bressol-crm-redemptions',
            [$this, 'renderRedemptionsPage']
        );

        add_submenu_page(
            'bressol_crm',
            'Ajustes CRM',
            'Ajustes',
            $capability,
            'bressol-crm-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function renderCustomersPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $this->handle_customer_post();

        echo '<div class="wrap">';
        echo '<h1>CRM - Clientes</h1>';
        settings_errors('bressol_crm');

        $customerId = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : 0;
        if ($customerId > 0) {
            $this->renderCustomerDetail($customerId);
            echo '</div>';
            return;
        }

        $filters = [
            'email' => isset($_GET['crm_email']) ? sanitize_text_field(wp_unslash($_GET['crm_email'])) : '',
            'type' => isset($_GET['crm_type']) ? sanitize_text_field(wp_unslash($_GET['crm_type'])) : '',
            'include_deleted' => !empty($_GET['crm_include_deleted']),
        ];

        $this->renderCustomerCreateForm();
        $this->renderImportForm();

        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $limit = 20;
        $offset = ($paged - 1) * $limit;
        $total = $this->customerService->count_customers($filters);
        $customers = $this->customerService->list_customers($filters, $limit, $offset);

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="bressol-crm" />';
        echo '<input type="text" name="crm_email" value="' . esc_attr($filters['email']) . '" placeholder="Email" />';
        echo '<select name="crm_type">';
        echo '<option value="">Tipo</option>';
        echo '<option value="b2c" ' . selected($filters['type'], 'b2c', false) . '>B2C</option>';
        echo '<option value="b2b" ' . selected($filters['type'], 'b2b', false) . '>B2B</option>';
        echo '</select>';
        echo '<label style="margin-left:12px;"><input type="checkbox" name="crm_include_deleted" value="1" ' . checked(!empty($filters['include_deleted']), true, false) . ' /> Incluir eliminados</label>';
        echo '<button class="button">Filtrar</button>';
        echo '</form>';

        $this->renderResultsSummary($paged, $limit, $total);

        echo '<table class="widefat striped" style="margin-top:16px;">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Email</th><th>Nombre</th><th>Tipo</th><th>Total</th><th>Pedidos</th><th>Último pedido</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';

        if ($customers === []) {
            echo '<tr><td colspan="8">No hay clientes.</td></tr>';
        }

        foreach ($customers as $customer) {
            $detailUrl = add_query_arg([
                'page' => 'bressol_crm',
                'customer_id' => $customer->id,
            ], admin_url('admin.php'));

            echo '<tr>';
            echo '<td>' . esc_html((string) $customer->id) . '</td>';
            echo '<td>' . esc_html((string) $customer->email) . '</td>';
            echo '<td>' . esc_html(trim((string) $customer->first_name . ' ' . (string) $customer->last_name)) . '</td>';
            echo '<td>' . esc_html((string) $customer->customer_type) . '</td>';
            echo '<td>' . esc_html(number_format((float) $customer->total_spent, 2)) . '</td>';
            echo '<td>' . esc_html((string) $customer->order_count) . '</td>';
            echo '<td>' . esc_html((string) ($customer->last_order_at ?? '-')) . '</td>';
            echo '<td><a class="button" href="' . esc_url($detailUrl) . '">Ver</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        $this->renderPagination($paged, $limit, $total, [
            'page' => 'bressol_crm',
            'crm_email' => $filters['email'],
            'crm_type' => $filters['type'],
            'crm_include_deleted' => $filters['include_deleted'] ? '1' : '',
        ]);

        echo '</div>';
    }

    public function renderLedgerPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $customerId = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : 0;
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $limit = 30;
        $offset = ($paged - 1) * $limit;

        $total = $this->pointsService->count_ledger($customerId ?: null);
        $entries = $this->pointsService->list_ledger($customerId ?: null, $limit, $offset);

        echo '<div class="wrap">';
        echo '<h1>Ledger de puntos</h1>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="bressol-crm-ledger" />';
        echo '<input type="number" name="customer_id" value="' . esc_attr((string) $customerId) . '" placeholder="ID cliente" />';
        echo '<button class="button">Filtrar</button>';
        echo '</form>';

        $this->renderResultsSummary($paged, $limit, $total);

        echo '<table class="widefat striped" style="margin-top:16px;">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Cliente</th><th>Origen</th><th>Source ID</th><th>Puntos</th><th>Status</th><th>Earned</th><th>Expires</th>';
        echo '</tr></thead><tbody>';

        if ($entries === []) {
            echo '<tr><td colspan="8">No hay movimientos.</td></tr>';
        }

        foreach ($entries as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $entry->id) . '</td>';
            echo '<td>' . esc_html((string) $entry->customer_id) . '</td>';
            echo '<td>' . esc_html((string) $entry->source_type) . '</td>';
            echo '<td>' . esc_html((string) ($entry->source_id ?? '-')) . '</td>';
            echo '<td>' . esc_html((string) $entry->points) . '</td>';
            echo '<td>' . esc_html((string) $entry->status) . '</td>';
            echo '<td>' . esc_html((string) $entry->earned_at) . '</td>';
            echo '<td>' . esc_html((string) $entry->expires_at) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        $this->renderPagination($paged, $limit, $total, [
            'page' => 'bressol-crm-ledger',
            'customer_id' => $customerId ?: '',
        ]);
        echo '</div>';
    }

    public function renderRedemptionsPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $customerId = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : 0;
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $limit = 30;
        $offset = ($paged - 1) * $limit;

        $total = $this->pointsService->count_redemptions($customerId ?: null);
        $redemptions = $this->pointsService->list_redemptions($customerId ?: null, $limit, $offset);

        echo '<div class="wrap">';
        echo '<h1>Redenciones</h1>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="bressol-crm-redemptions" />';
        echo '<input type="number" name="customer_id" value="' . esc_attr((string) $customerId) . '" placeholder="ID cliente" />';
        echo '<button class="button">Filtrar</button>';
        echo '</form>';

        $this->renderResultsSummary($paged, $limit, $total);

        echo '<table class="widefat striped" style="margin-top:16px;">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Cliente</th><th>Tipo</th><th>Puntos</th><th>Referencia</th><th>Fecha</th>';
        echo '</tr></thead><tbody>';

        if ($redemptions === []) {
            echo '<tr><td colspan="6">No hay redenciones.</td></tr>';
        }

        foreach ($redemptions as $redemption) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $redemption->id) . '</td>';
            echo '<td>' . esc_html((string) $redemption->customer_id) . '</td>';
            echo '<td>' . esc_html((string) $redemption->redemption_type) . '</td>';
            echo '<td>' . esc_html((string) $redemption->points_used) . '</td>';
            echo '<td>' . esc_html((string) ($redemption->reference ?? '-')) . '</td>';
            echo '<td>' . esc_html((string) $redemption->created_at) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        $this->renderPagination($paged, $limit, $total, [
            'page' => 'bressol-crm-redemptions',
            'customer_id' => $customerId ?: '',
        ]);
        echo '</div>';
    }

    public function renderSettingsPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $this->handle_settings_post();

        $settings = $this->settings->get_settings();

        echo '<div class="wrap">';
        echo '<h1>Ajustes CRM</h1>';
        settings_errors('bressol_crm');

        echo '<form method="post">';
        wp_nonce_field('bressol_crm_settings');
        echo '<table class="form-table">';
        echo '<tr><th>Puntos por euro B2C</th><td><input type="number" step="0.01" name="b2c_points_per_euro" value="' . esc_attr((string) $settings['b2c_points_per_euro']) . '" /></td></tr>';
        echo '<tr><th>Puntos por euro B2B</th><td><input type="number" step="0.01" name="b2b_points_per_euro" value="' . esc_attr((string) $settings['b2b_points_per_euro']) . '" /></td></tr>';
        echo '<tr><th>Meses de expiración</th><td><input type="number" min="1" name="expiry_months" value="' . esc_attr((string) $settings['expiry_months']) . '" /></td></tr>';
        echo '<tr><th>Meses de retención</th><td><input type="number" min="1" name="retention_months" value="' . esc_attr((string) $settings['retention_months']) . '" /></td></tr>';
        echo '<tr><th>Base de retención</th><td><select name="retention_basis">';
        echo '<option value="last_order" ' . selected((string) $settings['retention_basis'], 'last_order', false) . '>Último pedido</option>';
        echo '<option value="created_at" ' . selected((string) $settings['retention_basis'], 'created_at', false) . '>Fecha de alta</option>';
        echo '<option value="updated_at" ' . selected((string) $settings['retention_basis'], 'updated_at', false) . '>Última actualización</option>';
        echo '</select>';
        echo '<p class="description">Si la base es &quot;Último pedido&quot;, clientes sin pedidos no se anonimizan automáticamente.</p>';
        echo '</td></tr>';
        echo '<tr><th>Export GDPR</th><td><label><input type="checkbox" name="gdpr_export_enabled" value="1" ' . checked((int) $settings['gdpr_export_enabled'], 1, false) . ' /> Habilitar export GDPR/DSAR</label></td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" name="bressol_crm_settings_submit" class="button button-primary">Guardar</button></p>';
        echo '</form>';

        echo '</div>';
    }

    private function renderCustomerDetail(int $customerId): void
    {
        $customer = $this->customerService->get_customer($customerId);
        if (!$customer) {
            echo '<p>Cliente no encontrado.</p>';
            return;
        }

        $balance = $this->pointsService->get_balance($customerId);
        $consentState = $this->customerService->get_effective_marketing_state((string) $customer->email);
        $espStatus = $consentState['esp_status'] ?? null;
        $espDetails = $consentState['esp_details'] ?? null;
        $marketingDisabled = $espStatus === 'opt_out';
        $effectiveMarketing = $consentState['effective_flags']['can_receive_marketing'] ?? false;
        $crmProfiled = $consentState['crm_flags']['can_be_profiled'] ?? false;
        $crmMarketing = $consentState['crm_flags']['can_receive_marketing'] ?? false;
        $piiDisabled = (string) $customer->status === 'anonymized';
        $isDeleted = (string) $customer->status === 'deleted';
        $loyaltyEnabled = (int) ($customer->loyalty_enabled ?? 0) === 1;
        $loyaltyDisabled = (string) $customer->status !== 'active';

        echo '<a href="' . esc_url(admin_url('admin.php?page=bressol-crm')) . '">&larr; Volver al listado</a>';
        echo '<h2>Cliente #' . esc_html((string) $customer->id) . '</h2>';

        echo '<table class="widefat striped" style="max-width:900px;">';
        echo '<tbody>';
        echo '<tr><th>Email</th><td>' . esc_html((string) $customer->email) . '</td></tr>';
        echo '<tr><th>Nombre</th><td>' . esc_html(trim((string) $customer->first_name . ' ' . (string) $customer->last_name)) . '</td></tr>';
        echo '<tr><th>Tipo</th><td>' . esc_html((string) $customer->customer_type) . '</td></tr>';
        echo '<tr><th>Status</th><td>' . esc_html((string) $customer->status) . '</td></tr>';
        echo '<tr><th>Balance</th><td>' . esc_html((string) $balance) . '</td></tr>';
        echo '<tr><th>Total gastado</th><td>' . esc_html(number_format((float) $customer->total_spent, 2)) . '</td></tr>';
        echo '<tr><th>Pedidos</th><td>' . esc_html((string) $customer->order_count) . '</td></tr>';
        echo '<tr><th>Último pedido</th><td>' . esc_html((string) ($customer->last_order_at ?? '-')) . '</td></tr>';
        echo '</tbody></table>';

        echo '<h3>Consentimientos</h3>';
        echo '<table class="widefat striped" style="max-width:900px;">';
        echo '<tbody>';
        echo '<tr><th>CRM - Perfilado</th><td>' . esc_html($crmProfiled ? 'Sí' : 'No') . '</td></tr>';
        echo '<tr><th>CRM - Marketing</th><td>' . esc_html($crmMarketing ? 'Sí' : 'No') . '</td></tr>';
        echo '<tr><th>ESP - Estado</th><td>' . esc_html($espStatus ?? '-') . '</td></tr>';
        if (is_array($espDetails)) {
            if (!empty($espDetails['updated_at'])) {
                echo '<tr><th>ESP - Actualizado</th><td>' . esc_html((string) $espDetails['updated_at']) . '</td></tr>';
            }
            if (!empty($espDetails['source'])) {
                echo '<tr><th>ESP - Origen</th><td>' . esc_html((string) $espDetails['source']) . '</td></tr>';
            }
        }
        echo '<tr><th>Marketing efectivo</th><td>' . esc_html($effectiveMarketing ? 'Sí' : 'No') . '</td></tr>';
        echo '</tbody></table>';

        if ($marketingDisabled) {
            echo '<p class="notice notice-warning" style="padding:8px 12px;">ESP opt-out activo: no se puede activar marketing desde CRM.</p>';
        }
        if ($piiDisabled) {
            echo '<p class="notice notice-warning" style="padding:8px 12px;">Cliente anonimizado: edición de datos personales bloqueada.</p>';
        }
        if ($isDeleted) {
            echo '<p class="notice notice-warning" style="padding:8px 12px;">Cliente eliminado.</p>';
        }

        $timeline = $this->timelineService->get_timeline($customerId, 50);
        echo '<h3>Timeline</h3>';
        echo '<table class="widefat striped" style="max-width:900px;">';
        echo '<thead><tr><th>Fecha</th><th>Tipo</th><th>Resumen</th></tr></thead><tbody>';
        if ($timeline === []) {
            echo '<tr><td colspan="3">No hay eventos.</td></tr>';
        }
        foreach ($timeline as $event) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $event['date']) . '</td>';
            echo '<td>' . esc_html((string) $event['type']) . '</td>';
            echo '<td>' . esc_html((string) $event['summary']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<h3>Editar cliente</h3>';
        echo '<form method="post">';
        wp_nonce_field('bressol_crm_customer_update');
        echo '<input type="hidden" name="customer_id" value="' . esc_attr((string) $customer->id) . '" />';
        echo '<table class="form-table">';
        $editDisabled = $piiDisabled || $isDeleted;
        echo '<tr><th>Email</th><td><input type="email" name="email" value="' . esc_attr((string) $customer->email) . '" ' . ($editDisabled ? 'disabled' : '') . ' /></td></tr>';
        echo '<tr><th>Nombre</th><td><input type="text" name="first_name" value="' . esc_attr((string) $customer->first_name) . '" ' . ($editDisabled ? 'disabled' : '') . ' /></td></tr>';
        echo '<tr><th>Apellidos</th><td><input type="text" name="last_name" value="' . esc_attr((string) $customer->last_name) . '" ' . ($editDisabled ? 'disabled' : '') . ' /></td></tr>';
        echo '<tr><th>Teléfono</th><td><input type="text" name="phone" value="' . esc_attr((string) $customer->phone) . '" ' . ($editDisabled ? 'disabled' : '') . ' /></td></tr>';
        echo '<tr><th>Empresa</th><td><input type="text" name="company" value="' . esc_attr((string) $customer->company) . '" ' . ($editDisabled ? 'disabled' : '') . ' /></td></tr>';
        echo '<tr><th>Tipo</th><td><select name="customer_type">';
        echo '<option value="b2c" ' . selected((string) $customer->customer_type, 'b2c', false) . '>B2C</option>';
        echo '<option value="b2b" ' . selected((string) $customer->customer_type, 'b2b', false) . '>B2B</option>';
        echo '</select></td></tr>';
        echo '<tr><th>Business Type</th><td><input type="text" name="business_type" value="' . esc_attr((string) $customer->business_type) . '" ' . ($editDisabled ? 'disabled' : '') . ' /></td></tr>';
        echo '<tr><th>Perfilado</th><td><label><input type="checkbox" name="can_be_profiled" value="1" ' . checked((int) $customer->can_be_profiled, 1, false) . ' ' . ($editDisabled ? 'disabled' : '') . ' /> Permitido</label></td></tr>';
        echo '<tr><th>Marketing</th><td><label><input type="checkbox" name="can_receive_marketing" value="1" ' . checked((int) $customer->can_receive_marketing, 1, false) . ' ' . ($marketingDisabled || $editDisabled ? 'disabled' : '') . ' /> Permitido</label></td></tr>';
        echo '<tr><th>Programa de puntos</th><td><label><input type="checkbox" name="loyalty_enabled" value="1" ' . checked($loyaltyEnabled, true, false) . ' ' . ($loyaltyDisabled ? 'disabled' : '') . ' /> En programa de puntos</label></td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" name="bressol_crm_customer_submit" class="button button-primary" ' . ($editDisabled ? 'disabled' : '') . '>Guardar cambios</button></p>';
        echo '</form>';

        echo '<h3>Eliminar cliente</h3>';
        echo '<form method="post" onsubmit="return confirm(\'¿Eliminar cliente?\');">';
        wp_nonce_field('bressol_crm_customer_delete');
        echo '<input type="hidden" name="customer_id" value="' . esc_attr((string) $customer->id) . '" />';
        echo '<p class="submit"><button type="submit" name="bressol_crm_customer_delete_submit" class="button button-secondary" ' . ($isDeleted ? 'disabled' : '') . '>Eliminar cliente</button></p>';
        echo '</form>';

        echo '<h3>Restaurar cliente</h3>';
        echo '<form method="post" onsubmit="return confirm(\'¿Restaurar cliente?\');">';
        wp_nonce_field('bressol_crm_customer_restore');
        echo '<input type="hidden" name="customer_id" value="' . esc_attr((string) $customer->id) . '" />';
        echo '<p class="submit"><button type="submit" name="bressol_crm_customer_restore_submit" class="button" ' . ($isDeleted ? '' : 'disabled') . '>Restaurar cliente</button></p>';
        echo '</form>';

        echo '<h3>Anonimizar cliente</h3>';
        echo '<form method="post" onsubmit="return confirm(\'¿Anonimizar cliente?\');">';
        wp_nonce_field('bressol_crm_customer_anonymize');
        echo '<input type="hidden" name="customer_id" value="' . esc_attr((string) $customer->id) . '" />';
        echo '<p class="submit"><button type="submit" name="bressol_crm_customer_anonymize_submit" class="button" ' . ($isDeleted ? 'disabled' : '') . '>Anonimizar</button></p>';
        echo '</form>';

        if ($this->settings->is_gdpr_export_enabled()) {
            echo '<h3>Exportar datos</h3>';
            echo '<form method="post">';
            wp_nonce_field('bressol_crm_customer_export');
            echo '<input type="hidden" name="customer_id" value="' . esc_attr((string) $customer->id) . '" />';
            echo '<p class="submit"><button type="submit" name="bressol_crm_customer_export_submit" class="button">Exportar datos (GDPR)</button></p>';
            echo '</form>';
        }

        echo '<h3>Canjear puntos</h3>';
        echo '<form method="post">';
        wp_nonce_field('bressol_crm_redemption');
        echo '<input type="hidden" name="customer_id" value="' . esc_attr((string) $customer->id) . '" />';
        echo '<table class="form-table">';
        echo '<tr><th>Puntos</th><td><input type="number" min="1" name="points_used" value="" ' . ($isDeleted ? 'disabled' : '') . ' /></td></tr>';
        echo '<tr><th>Tipo</th><td><input type="text" name="redemption_type" value="manual" ' . ($isDeleted ? 'disabled' : '') . ' /></td></tr>';
        echo '<tr><th>Referencia</th><td><input type="text" name="reference" value="" ' . ($isDeleted ? 'disabled' : '') . ' /></td></tr>';
        echo '<tr><th>Notas</th><td><textarea name="notes" rows="3" cols="40" ' . ($isDeleted ? 'disabled' : '') . '></textarea></td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" name="bressol_crm_redemption_submit" class="button" ' . ($isDeleted ? 'disabled' : '') . '>Registrar canje</button></p>';
        echo '</form>';
    }

    private function handle_customer_post(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!$this->current_user_can()) {
            return;
        }

        if (isset($_POST['bressol_crm_customer_create_submit'])) {
            check_admin_referer('bressol_crm_customer_create');

            $payload = wp_unslash($_POST);
            $result = $this->customerService->create_from_admin($payload);

            if ($result['ok']) {
                add_settings_error('bressol_crm', 'customer_created', 'Cliente creado.', 'updated');
            } else {
                add_settings_error('bressol_crm', $result['code'] ?? 'customer_create_failed', $result['error'] ?? 'No se pudo crear el cliente.', 'error');
            }

            if (!empty($result['warning'])) {
                add_settings_error('bressol_crm', $result['warning_code'] ?? 'customer_create_warning', $result['warning'], 'warning');
            }
        }

        if (isset($_POST['bressol_crm_customer_submit'])) {
            check_admin_referer('bressol_crm_customer_update');

            $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
            if ($customerId > 0) {
                $payload = wp_unslash($_POST);
                $result = $this->customerService->update_from_admin($customerId, $payload);
                if ($result['ok']) {
                    add_settings_error('bressol_crm', 'customer_saved', 'Cliente actualizado.', 'updated');
                } else {
                    add_settings_error('bressol_crm', $result['code'] ?? 'customer_update_failed', $result['error'] ?? 'No se pudo actualizar el cliente.', 'error');
                }
            }
        }

        if (isset($_POST['bressol_crm_customer_delete_submit'])) {
            check_admin_referer('bressol_crm_customer_delete');

            $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
            if ($customerId <= 0) {
                add_settings_error('bressol_crm', 'customer_delete_invalid', 'Cliente inválido.', 'error');
                return;
            }

            $deleted = $this->customerService->soft_delete_customer($customerId, get_current_user_id());
            if ($deleted) {
                add_settings_error('bressol_crm', 'customer_deleted', 'Cliente eliminado.', 'updated');
            } else {
                add_settings_error('bressol_crm', 'customer_delete_failed', 'No se pudo eliminar el cliente.', 'error');
            }
        }

        if (isset($_POST['bressol_crm_customer_restore_submit'])) {
            check_admin_referer('bressol_crm_customer_restore');

            $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
            if ($customerId <= 0) {
                add_settings_error('bressol_crm', 'customer_restore_invalid', 'Cliente inválido.', 'error');
                return;
            }

            $restored = $this->customerService->restore_customer($customerId, get_current_user_id());
            if ($restored) {
                add_settings_error('bressol_crm', 'customer_restored', 'Cliente restaurado.', 'updated');
            } else {
                add_settings_error('bressol_crm', 'customer_restore_failed', 'No se pudo restaurar el cliente.', 'error');
            }
        }

        if (isset($_POST['bressol_crm_customer_anonymize_submit'])) {
            check_admin_referer('bressol_crm_customer_anonymize');

            $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
            if ($customerId <= 0) {
                add_settings_error('bressol_crm', 'customer_anonymize_invalid', 'Cliente inválido.', 'error');
                return;
            }

            $anonymized = $this->customerService->anonymize_customer($customerId);
            if ($anonymized) {
                add_settings_error('bressol_crm', 'customer_anonymized', 'Cliente anonimizado.', 'updated');
            } else {
                add_settings_error('bressol_crm', 'customer_anonymize_failed', 'No se pudo anonimizar el cliente.', 'error');
            }
        }

        if (isset($_POST['bressol_crm_customer_export_submit'])) {
            check_admin_referer('bressol_crm_customer_export');

            if (!$this->settings->is_gdpr_export_enabled()) {
                add_settings_error('bressol_crm', 'gdpr_export_disabled', 'Export GDPR desactivado.', 'error');
                return;
            }

            if (!$this->settings->is_gdpr_export_enabled()) {
                add_settings_error('bressol_crm', 'gdpr_export_disabled', 'Export GDPR desactivado.', 'error');
                return;
            }

            if (!$this->settings->is_gdpr_export_enabled()) {
                add_settings_error('bressol_crm', 'gdpr_export_disabled', 'Export GDPR desactivado.', 'error');
                return;
            }

            if (!$this->settings->is_gdpr_export_enabled()) {
                add_settings_error('bressol_crm', 'gdpr_export_disabled', 'Export GDPR desactivado.', 'error');
                return;
            }

            $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
            if ($customerId <= 0) {
                add_settings_error('bressol_crm', 'customer_export_invalid', 'Cliente inválido.', 'error');
                return;
            }

            $data = $this->customerService->get_export_data($customerId);
            if ($data === []) {
                add_settings_error('bressol_crm', 'customer_export_failed', 'No se pudo exportar el cliente.', 'error');
                return;
            }

            $this->auditLogger->log('customer_exported', 'customer', $customerId, get_current_user_id(), [
                'source' => 'admin',
            ]);

            nocache_headers();
            header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            header('Content-Disposition: attachment; filename=crm-customer-' . $customerId . '.json');
            echo wp_json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (isset($_POST['bressol_crm_redemption_submit'])) {
            check_admin_referer('bressol_crm_redemption');

            $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
            $pointsUsed = isset($_POST['points_used']) ? absint($_POST['points_used']) : 0;
            $redemptionType = isset($_POST['redemption_type']) ? sanitize_text_field(wp_unslash($_POST['redemption_type'])) : 'manual';
            $reference = isset($_POST['reference']) ? sanitize_text_field(wp_unslash($_POST['reference'])) : null;
            $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : null;

            if ($customerId > 0 && $pointsUsed > 0) {
                $customer = $this->customerService->get_customer($customerId);
                if (!$customer) {
                    add_settings_error('bressol_crm', 'customer_not_found', 'Cliente no encontrado.', 'error');
                    return;
                }
                if ((string) $customer->status !== 'active') {
                    add_settings_error('bressol_crm', 'customer_status_locked', 'Cliente no activo. No se pueden registrar canjes.', 'error');
                    return;
                }

                if (!$this->customerService->is_loyalty_enabled($customerId)) {
                    add_settings_error('bressol_crm', 'customer_loyalty_required', 'Cliente fuera del programa de puntos. No se pueden registrar canjes.', 'error');
                    return;
                }

                $balance = $this->pointsService->get_balance($customerId);
                if ($balance < $pointsUsed) {
                    add_settings_error('bressol_crm', 'insufficient_points', 'Saldo insuficiente para canje.', 'error');
                    return;
                }

                $redemptionId = $this->pointsService->create_redemption($customerId, $pointsUsed, $redemptionType, $reference, $notes);
                if ($redemptionId) {
                    add_settings_error('bressol_crm', 'redemption_saved', 'Redención registrada.', 'updated');
                } else {
                    add_settings_error('bressol_crm', 'redemption_failed', 'No se pudo registrar la redención.', 'error');
                }
            }
        }

        if (isset($_POST['bressol_crm_import_submit'])) {
            check_admin_referer('bressol_crm_import');

            $status = isset($_POST['import_statuses']) ? sanitize_text_field(wp_unslash($_POST['import_statuses'])) : 'completed,processing';
            $after = isset($_POST['import_after']) ? sanitize_text_field(wp_unslash($_POST['import_after'])) : '';
            $before = isset($_POST['import_before']) ? sanitize_text_field(wp_unslash($_POST['import_before'])) : '';
            $limit = isset($_POST['import_limit']) ? max(1, (int) wp_unslash($_POST['import_limit'])) : 50;
            $offset = isset($_POST['import_offset']) ? max(0, (int) wp_unslash($_POST['import_offset'])) : 0;
            $withPoints = !empty($_POST['import_with_points']);
            $prevProcessed = isset($_POST['import_processed']) ? max(0, (int) wp_unslash($_POST['import_processed'])) : 0;
            $prevCreated = isset($_POST['import_created']) ? max(0, (int) wp_unslash($_POST['import_created'])) : 0;
            $prevUpdated = isset($_POST['import_updated']) ? max(0, (int) wp_unslash($_POST['import_updated'])) : 0;
            $prevSkipped = isset($_POST['import_skipped']) ? max(0, (int) wp_unslash($_POST['import_skipped'])) : 0;
            $prevErrors = isset($_POST['import_errors']) ? max(0, (int) wp_unslash($_POST['import_errors'])) : 0;
            $prevPointsAwarded = isset($_POST['import_points_awarded']) ? max(0, (int) wp_unslash($_POST['import_points_awarded'])) : 0;
            $prevPointsSkipped = isset($_POST['import_points_skipped_no_loyalty']) ? max(0, (int) wp_unslash($_POST['import_points_skipped_no_loyalty'])) : 0;
            $prevOrdersWithoutEmail = isset($_POST['import_orders_without_email']) ? max(0, (int) wp_unslash($_POST['import_orders_without_email'])) : 0;
            $prevInvalidOrders = isset($_POST['import_invalid_orders']) ? max(0, (int) wp_unslash($_POST['import_invalid_orders'])) : 0;

            $results = $this->importer->run([
                'status' => $status,
                'after' => $after,
                'before' => $before,
                'limit' => $limit,
                'offset' => $offset,
                'with_points' => $withPoints,
                'logger' => null,
            ]);

            if (!empty($results['error'])) {
                add_settings_error('bressol_crm', 'import_failed', (string) $results['error'], 'error');
                return;
            }

            $totals = $results['totals'] ?? [];
            $nextOffset = $offset + $limit;
            $batchCount = (int) ($results['batch_count'] ?? 0);

            $processed = $prevProcessed + (int) ($totals['orders_processed'] ?? 0);
            $created = $prevCreated + (int) ($totals['customers_created'] ?? 0);
            $updated = $prevUpdated + (int) ($totals['customers_updated'] ?? 0);
            $skipped = $prevSkipped + (int) ($totals['skipped'] ?? 0);
            $errors = $prevErrors + (int) ($totals['errors'] ?? 0);
            $pointsAwarded = $prevPointsAwarded + (int) ($totals['points_awarded'] ?? 0);
            $pointsSkipped = $prevPointsSkipped + (int) ($totals['points_skipped_no_loyalty'] ?? 0);
            $ordersWithoutEmail = $prevOrdersWithoutEmail + (int) ($totals['orders_without_email'] ?? 0);
            $invalidOrders = $prevInvalidOrders + (int) ($totals['invalid_orders'] ?? 0);

            if ($batchCount < $limit) {
                if ($batchCount === 0) {
                    add_settings_error('bressol_crm', 'import_finished_empty', 'No hay más pedidos para importar.', 'updated');
                } else {
                    add_settings_error('bressol_crm', 'import_finished', 'Import finalizado.', 'updated');
                }
                $results = [
                    'import_processed' => $processed,
                    'import_created' => $created,
                    'import_updated' => $updated,
                    'import_skipped' => $skipped,
                    'import_errors' => $errors,
                    'import_points_awarded' => $pointsAwarded,
                    'import_points_skipped_no_loyalty' => $pointsSkipped,
                    'import_orders_without_email' => $ordersWithoutEmail,
                    'import_invalid_orders' => $invalidOrders,
                ];
                $_GET = array_merge($_GET, $results);
                return;
            }

            $redirectArgs = [
                'page' => 'bressol_crm',
                'import_statuses' => $status,
                'import_after' => $after,
                'import_before' => $before,
                'import_with_points' => $withPoints ? '1' : '',
                'import_limit' => $limit,
                'import_offset' => $nextOffset,
                'import_processed' => $processed,
                'import_created' => $created,
                'import_updated' => $updated,
                'import_skipped' => $skipped,
                'import_errors' => $errors,
                'import_points_awarded' => $pointsAwarded,
                'import_points_skipped_no_loyalty' => $pointsSkipped,
                'import_orders_without_email' => $ordersWithoutEmail,
                'import_invalid_orders' => $invalidOrders,
            ];

            wp_safe_redirect(add_query_arg($redirectArgs, admin_url('admin.php')));
            exit;
        }
    }

    private function handle_settings_post(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['bressol_crm_settings_submit'])) {
            return;
        }

        if (!$this->current_user_can()) {
            return;
        }

        check_admin_referer('bressol_crm_settings');

        $payload = [
            'b2c_points_per_euro' => isset($_POST['b2c_points_per_euro']) ? (float) wp_unslash($_POST['b2c_points_per_euro']) : null,
            'b2b_points_per_euro' => isset($_POST['b2b_points_per_euro']) ? (float) wp_unslash($_POST['b2b_points_per_euro']) : null,
            'expiry_months' => isset($_POST['expiry_months']) ? (int) wp_unslash($_POST['expiry_months']) : null,
            'retention_months' => isset($_POST['retention_months']) ? (int) wp_unslash($_POST['retention_months']) : null,
            'retention_basis' => isset($_POST['retention_basis']) ? sanitize_text_field(wp_unslash($_POST['retention_basis'])) : null,
            'gdpr_export_enabled' => isset($_POST['gdpr_export_enabled']) ? (int) wp_unslash($_POST['gdpr_export_enabled']) : 0,
        ];

        $this->settings->update_settings($payload);
        add_settings_error('bressol_crm', 'settings_saved', 'Ajustes guardados.', 'updated');
    }

    private function renderPagination(int $paged, int $limit, int $total, array $baseArgs): void
    {
        $totalPages = (int) ceil($total / $limit);
        if ($totalPages <= 1) {
            return;
        }

        $paged = min(max(1, $paged), $totalPages);
        $firstDisabled = $paged <= 1;
        $lastDisabled = $paged >= $totalPages;

        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo '<span class="displaying-num">' . esc_html($paged . ' / ' . $totalPages) . '</span> ';

        $this->renderPageLink($firstDisabled, 1, '« Primera', $baseArgs);
        $this->renderPageLink($firstDisabled, $paged - 1, '‹ Anterior', $baseArgs);
        $this->renderPageLink($lastDisabled, $paged + 1, 'Siguiente ›', $baseArgs);
        $this->renderPageLink($lastDisabled, $totalPages, 'Última »', $baseArgs);

        echo '</div></div>';
    }

    private function renderPageLink(bool $disabled, int $page, string $label, array $baseArgs): void
    {
        if ($disabled) {
            echo '<span class="tablenav-pages-navspan" aria-hidden="true">' . esc_html($label) . '</span> ';
            return;
        }

        $url = add_query_arg(array_merge($baseArgs, ['paged' => $page]), admin_url('admin.php'));
        echo '<a class="button" href="' . esc_url($url) . '">' . esc_html($label) . '</a> ';
    }

    private function renderResultsSummary(int $paged, int $limit, int $total): void
    {
        if ($total <= 0) {
            echo '<div class="tablenav"><div class="tablenav-pages">Mostrando 0–0 de 0</div></div>';
            return;
        }

        $totalPages = (int) ceil($total / $limit);
        $paged = min(max(1, $paged), $totalPages);
        $start = ($paged - 1) * $limit + 1;
        $end = min($start + $limit - 1, $total);

        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo 'Mostrando ' . esc_html((string) $start) . '–' . esc_html((string) $end) . ' de ' . esc_html((string) $total);
        echo '</div></div>';
    }

    private function renderCustomerCreateForm(): void
    {
        echo '<h2>Crear cliente</h2>';
        echo '<form method="post" style="margin:16px 0;">';
        wp_nonce_field('bressol_crm_customer_create');
        echo '<table class="form-table">';
        echo '<tr><th>Email *</th><td><input type="email" name="email" required /></td></tr>';
        echo '<tr><th>Tipo *</th><td><select name="customer_type" required>';
        echo '<option value="b2c">B2C</option>';
        echo '<option value="b2b">B2B</option>';
        echo '</select></td></tr>';
        echo '<tr><th>Nombre</th><td><input type="text" name="first_name" /></td></tr>';
        echo '<tr><th>Apellidos</th><td><input type="text" name="last_name" /></td></tr>';
        echo '<tr><th>Teléfono</th><td><input type="text" name="phone" /></td></tr>';
        echo '<tr><th>Empresa</th><td><input type="text" name="company" /></td></tr>';
        echo '<tr><th>Business Type</th><td><input type="text" name="business_type" /></td></tr>';
        echo '<tr><th>Perfilado</th><td><label><input type="checkbox" name="can_be_profiled" value="1" checked /> Permitido</label></td></tr>';
        echo '<tr><th>Marketing</th><td><label><input type="checkbox" name="can_receive_marketing" value="1" /> Permitido</label></td></tr>';
        echo '<tr><th>Programa de puntos</th><td><label><input type="checkbox" name="loyalty_enabled" value="1" /> En programa de puntos</label></td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" name="bressol_crm_customer_create_submit" class="button button-primary">Crear cliente</button></p>';
        echo '</form>';
    }

    private function renderImportForm(): void
    {
        $status = isset($_GET['import_statuses']) ? sanitize_text_field(wp_unslash($_GET['import_statuses'])) : 'completed,processing';
        $after = isset($_GET['import_after']) ? sanitize_text_field(wp_unslash($_GET['import_after'])) : '';
        $before = isset($_GET['import_before']) ? sanitize_text_field(wp_unslash($_GET['import_before'])) : '';
        $limit = isset($_GET['import_limit']) ? max(1, (int) wp_unslash($_GET['import_limit'])) : 50;
        $offset = isset($_GET['import_offset']) ? max(0, (int) wp_unslash($_GET['import_offset'])) : 0;
        $withPoints = !empty($_GET['import_with_points']);

        $processed = isset($_GET['import_processed']) ? (int) wp_unslash($_GET['import_processed']) : 0;
        $created = isset($_GET['import_created']) ? (int) wp_unslash($_GET['import_created']) : 0;
        $updated = isset($_GET['import_updated']) ? (int) wp_unslash($_GET['import_updated']) : 0;
        $skipped = isset($_GET['import_skipped']) ? (int) wp_unslash($_GET['import_skipped']) : 0;
        $errors = isset($_GET['import_errors']) ? (int) wp_unslash($_GET['import_errors']) : 0;
        $pointsAwarded = isset($_GET['import_points_awarded']) ? (int) wp_unslash($_GET['import_points_awarded']) : 0;
        $pointsSkipped = isset($_GET['import_points_skipped_no_loyalty']) ? (int) wp_unslash($_GET['import_points_skipped_no_loyalty']) : 0;
        $ordersWithoutEmail = isset($_GET['import_orders_without_email']) ? (int) wp_unslash($_GET['import_orders_without_email']) : 0;
        $invalidOrders = isset($_GET['import_invalid_orders']) ? (int) wp_unslash($_GET['import_invalid_orders']) : 0;

        echo '<h2>Importar desde WooCommerce</h2>';
        echo '<form method="post" style="margin:16px 0;">';
        wp_nonce_field('bressol_crm_import');
        echo '<input type="hidden" name="import_processed" value="' . esc_attr((string) $processed) . '" />';
        echo '<input type="hidden" name="import_created" value="' . esc_attr((string) $created) . '" />';
        echo '<input type="hidden" name="import_updated" value="' . esc_attr((string) $updated) . '" />';
        echo '<input type="hidden" name="import_skipped" value="' . esc_attr((string) $skipped) . '" />';
        echo '<input type="hidden" name="import_errors" value="' . esc_attr((string) $errors) . '" />';
        echo '<input type="hidden" name="import_points_awarded" value="' . esc_attr((string) $pointsAwarded) . '" />';
        echo '<input type="hidden" name="import_points_skipped_no_loyalty" value="' . esc_attr((string) $pointsSkipped) . '" />';
        echo '<input type="hidden" name="import_orders_without_email" value="' . esc_attr((string) $ordersWithoutEmail) . '" />';
        echo '<input type="hidden" name="import_invalid_orders" value="' . esc_attr((string) $invalidOrders) . '" />';
        echo '<table class="form-table">';
        echo '<tr><th>Statuses</th><td><input type="text" name="import_statuses" value="' . esc_attr($status) . '" placeholder="completed,processing" /></td></tr>';
        echo '<tr><th>After</th><td><input type="date" name="import_after" value="' . esc_attr($after) . '" /></td></tr>';
        echo '<tr><th>Before</th><td><input type="date" name="import_before" value="' . esc_attr($before) . '" /></td></tr>';
        echo '<tr><th>Batch size</th><td><input type="number" min="1" name="import_limit" value="' . esc_attr((string) $limit) . '" /></td></tr>';
        echo '<tr><th>Offset</th><td><input type="number" min="0" name="import_offset" value="' . esc_attr((string) $offset) . '" /></td></tr>';
        echo '<tr><th>Incluir puntos</th><td><label><input type="checkbox" name="import_with_points" value="1" ' . checked($withPoints, true, false) . ' /> Sí</label></td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" name="bressol_crm_import_submit" class="button">Iniciar import</button></p>';
        if ($offset > 0) {
            echo '<p><button type="submit" name="bressol_crm_import_submit" class="button">Continuar import</button></p>';
        }
        echo '</form>';

        echo '<div style="margin:8px 0;">';
        echo '<strong>Progreso:</strong> ';
        echo 'Offset actual: ' . esc_html((string) $offset);
        echo ' | Procesados: ' . esc_html((string) $processed);
        echo ' | Creados: ' . esc_html((string) $created);
        echo ' | Actualizados: ' . esc_html((string) $updated);
        echo ' | Omitidos: ' . esc_html((string) $skipped);
        echo ' | Errores: ' . esc_html((string) $errors);
        echo ' | Puntos otorgados: ' . esc_html((string) $pointsAwarded);
        echo ' | Puntos omitidos (no loyalty): ' . esc_html((string) $pointsSkipped);
        echo ' | Pedidos sin email: ' . esc_html((string) $ordersWithoutEmail);
        echo ' | Pedidos inválidos: ' . esc_html((string) $invalidOrders);
        echo '</div>';
    }

    private function get_capability(): string
    {
        return Capabilities::CAP;
    }

    private function current_user_can(): bool
    {
        return current_user_can($this->get_capability());
    }
}
