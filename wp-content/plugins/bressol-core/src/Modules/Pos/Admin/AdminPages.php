<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Admin;

use Bressol\Modules\Crm\Services\AuditLogger;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Pos\Services\PosSettings;
use Bressol\Modules\Pos\Services\CustomerLookupService;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private PosSettings $settings;
    private CustomerService $customerService;
    private CustomerLookupService $lookupService;

    public function __construct(
        PosSettings $settings,
        CustomerService $customerService,
        CustomerLookupService $lookupService
    )
    {
        $this->settings = $settings;
        $this->customerService = $customerService;
        $this->lookupService = $lookupService;
    }

    public function registerMenus(): void
    {
        $capability = $this->get_capability();

        add_menu_page(
            'POS',
            'POS',
            $capability,
            'bressol-pos',
            [$this, 'renderNewSalePage'],
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'bressol-pos',
            'Nueva venta',
            'Nueva venta',
            $capability,
            'bressol-pos',
            [$this, 'renderNewSalePage']
        );

        add_submenu_page(
            'bressol-pos',
            'Clientes POS',
            'Clientes POS',
            $capability,
            'bressol-pos-customers',
            [$this, 'renderCustomersPage']
        );

        add_submenu_page(
            'bressol-pos',
            'Mercados y ajustes',
            'Mercados y ajustes',
            $capability,
            'bressol-pos-settings',
            [$this, 'renderSettingsPage']
        );

        add_submenu_page(
            'bressol-pos',
            'Reportes',
            'Reportes',
            $capability,
            'bressol-pos-reports',
            [$this, 'renderReportsPage']
        );
    }

    public function renderNewSalePage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        echo '<div class="wrap bressol-pos">';
        echo '<h1>POS - Nueva venta</h1>';
        echo '<div id="bressol-pos-root" class="bressol-pos__layout">';
        echo '<div class="bressol-pos__panel">';
        echo '<h2>Mercado</h2>';
        echo '<label>Mercado *</label>';
        echo '<select data-pos-market-select required>';
        echo '<option value="">Selecciona un mercado</option>';
        echo '</select>';
        echo '<label style="margin-top:8px;">Coste mercado (€)</label>';
        echo '<input type="number" min="0" step="0.01" data-pos-market-cost value="0" />';
        echo '</div>';

        echo '<div class="bressol-pos__panel">';
        echo '<h2>Cliente</h2>';
        echo '<label>Token / ID</label>';
        echo '<div class="bressol-pos__row">';
        echo '<input type="text" data-pos-customer-token placeholder="Token o ID" />';
        echo '<button type="button" class="button" data-pos-customer-search>Buscar</button>';
        echo '<button type="button" class="button" data-pos-customer-clear>Limpiar</button>';
        echo '</div>';
        echo '<label style="margin-top:8px;"><input type="checkbox" data-pos-anonymous-toggle /> Venta anónima</label>';
        echo '<p data-pos-customer-summary class="bressol-pos__muted">Venta anónima</p>';
        echo '<div class="bressol-pos__row">';
        echo '<label><input type="checkbox" data-pos-loyalty-opt /> Loyalty</label>';
        echo '<label><input type="checkbox" data-pos-marketing-opt /> Marketing</label>';
        echo '</div>';
        echo '<div class="bressol-pos__panel" style="margin-top:12px;">';
        echo '<h3>Canje de puntos</h3>';
        echo '<label>Puntos a canjear</label>';
        echo '<input type="number" min="0" data-pos-points-redeem value="0" />';
        echo '<p class="bressol-pos__muted">Valor: <span data-pos-points-value>0,00 €</span></p>';
        echo '<p class="bressol-pos__muted" data-pos-points-notice></p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="bressol-pos__panel">';
        echo '<h2>Catálogo</h2>';
        echo '<div class="bressol-pos__row">';
        echo '<input type="text" data-pos-product-query placeholder="Buscar por nombre o SKU" />';
        echo '<button type="button" class="button" data-pos-product-search>Buscar</button>';
        echo '</div>';
        echo '<div data-pos-product-results class="bressol-pos__results"></div>';
        echo '</div>';

        echo '<div class="bressol-pos__panel">';
        echo '<h2>Carrito</h2>';
        echo '<table class="widefat striped bressol-pos__cart">';
        echo '<thead><tr><th>Producto</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>';
        echo '<tbody data-pos-cart-body></tbody>';
        echo '</table>';
        echo '<div class="bressol-pos__total bressol-pos__total--secondary">';
        echo '<span>Descuento puntos</span>';
        echo '<strong data-pos-redemption-total>0,00 €</strong>';
        echo '</div>';
        echo '<div class="bressol-pos__total">';
        echo '<span>Total neto</span>';
        echo '<strong data-pos-cart-total>0,00 €</strong>';
        echo '</div>';
        echo '<div class="bressol-pos__row">';
        echo '<button type="button" class="button button-primary" data-pos-create-order>Crear pedido</button>';
        echo '<span data-pos-feedback class="bressol-pos__feedback"></span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function renderCustomersPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $this->handle_customer_create_post();
        $lookupResult = $this->handle_customer_regenerate_post();
        if ($lookupResult === null) {
            $lookupResult = $this->handle_customer_lookup_post();
        }

        echo '<div class="wrap bressol-pos">';
        echo '<h1>POS - Clientes</h1>';
        settings_errors('bressol_pos_customers');

        echo '<h2>Buscar cliente por token/ID</h2>';
        echo '<form method="post" style="margin:16px 0;">';
        wp_nonce_field('bressol_pos_customer_lookup');
        echo '<input type="text" name="pos_public_id" placeholder="Token o ID" required />';
        echo '<button type="submit" name="bressol_pos_customer_search_submit" class="button">Buscar</button>';
        echo '</form>';

        if (is_array($lookupResult)) {
            echo '<h3>Resultado</h3>';
            echo '<table class="widefat striped" style="max-width:700px;">';
            echo '<tbody>';
            echo '<tr><th>ID</th><td>' . esc_html((string) $lookupResult['customer_id']) . '</td></tr>';
            echo '<tr><th>Nombre</th><td>' . esc_html((string) $lookupResult['display_name']) . '</td></tr>';
            echo '<tr><th>Token</th><td>' . esc_html((string) $lookupResult['masked_public_id']) . '</td></tr>';
            echo '<tr><th>Loyalty</th><td>' . esc_html($lookupResult['loyalty_enabled'] ? 'Sí' : 'No') . '</td></tr>';
            echo '<tr><th>Marketing efectivo</th><td>' . esc_html($lookupResult['marketing_effective'] ? 'Sí' : 'No') . '</td></tr>';
            echo '<tr><th>Status</th><td>' . esc_html((string) $lookupResult['status']) . '</td></tr>';
            echo '</tbody></table>';

            echo '<form method="post" style="margin-top:12px;">';
            wp_nonce_field('bressol_pos_customer_regenerate');
            echo '<input type="hidden" name="customer_id" value="' . esc_attr((string) $lookupResult['customer_id']) . '" />';
            echo '<button type="submit" name="bressol_pos_customer_regenerate_submit" class="button">Regenerar ID</button>';
            echo '</form>';
        }

        echo '<h2>Alta rápida</h2>';
        echo '<form method="post" style="margin:16px 0;max-width:720px;">';
        wp_nonce_field('bressol_pos_customer_create');
        echo '<table class="form-table">';
        echo '<tr><th>Nombre *</th><td><input type="text" name="first_name" required /></td></tr>';
        echo '<tr><th>Apellidos</th><td><input type="text" name="last_name" /></td></tr>';
        echo '<tr><th>Email (opcional)</th><td><input type="email" name="email" /></td></tr>';
        echo '<tr><th>Teléfono (opcional)</th><td><input type="text" name="phone" /></td></tr>';
        echo '<tr><th>Tipo</th><td><select name="customer_type">';
        echo '<option value="b2c">B2C</option>';
        echo '<option value="b2b">B2B</option>';
        echo '</select></td></tr>';
        echo '<tr><th>Loyalty</th><td><label><input type="checkbox" name="loyalty_enabled" value="1" /> Activar programa</label></td></tr>';
        echo '<tr><th>Marketing</th><td><label><input type="checkbox" name="can_receive_marketing" value="1" /> Opt-in marketing</label></td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" name="bressol_pos_customer_create_submit" class="button button-primary">Crear cliente</button></p>';
        echo '</form>';
        echo '</div>';
    }

    public function renderSettingsPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }
        $this->handle_settings_post();
        $this->handle_markets_post();

        $settings = $this->settings->get_settings();
        $markets = $this->settings->get_markets();
        $statuses = $this->settings->get_allowed_order_statuses();

        echo '<div class="wrap bressol-pos">';
        echo '<h1>POS - Mercados y ajustes</h1>';
        settings_errors('bressol_pos');

        echo '<h2>Ajustes generales</h2>';
        echo '<form method="post" style="margin:16px 0;">';
        wp_nonce_field('bressol_pos_settings');
        echo '<table class="form-table">';
        echo '<tr><th>Estado pedido por defecto</th><td><select name="order_status_default">';
        foreach ($statuses as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '" ' . selected((string) $settings['order_status_default'], (string) $slug, false) . '>'
                . esc_html((string) $label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>Valor puntos (céntimos)</th><td><input type="number" min="1" name="points_value_cents" value="' . esc_attr((string) $settings['points_value_cents']) . '" /></td></tr>';
        echo '<tr><th>Min. puntos canje</th><td><input type="number" min="0" name="min_redemption_points" value="' . esc_attr((string) $settings['min_redemption_points']) . '" /></td></tr>';
        echo '<tr><th>Máx. % canje pedido</th><td><input type="number" min="0" max="100" name="max_redemption_percent_of_order" value="' . esc_attr((string) $settings['max_redemption_percent_of_order']) . '" /></td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" name="bressol_pos_settings_submit" class="button button-primary">Guardar ajustes</button></p>';
        echo '</form>';

        echo '<h2>Mercados</h2>';
        echo '<form method="post" style="margin:16px 0;">';
        wp_nonce_field('bressol_pos_market_add');
        echo '<table class="form-table">';
        echo '<tr><th>Nombre</th><td><input type="text" name="market_name" required /></td></tr>';
        echo '<tr><th>Activo</th><td><label><input type="checkbox" name="market_active" value="1" checked /> Activo</label></td></tr>';
        echo '<tr><th>Coste por defecto (€)</th><td><input type="number" step="0.01" min="0" name="market_default_cost" value="0" /></td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" name="bressol_pos_market_add_submit" class="button">Añadir mercado</button></p>';
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Nombre</th><th>Activo</th><th>Coste (€)</th><th>Acciones</th></tr></thead><tbody>';
        if ($markets === []) {
            echo '<tr><td colspan="4">No hay mercados configurados.</td></tr>';
        }
        foreach ($markets as $market) {
            $marketId = (string) ($market['id'] ?? '');
            $name = (string) ($market['name'] ?? '');
            $active = !empty($market['active']);
            $costCents = (int) ($market['default_cost_cents'] ?? 0);
            $costEuros = number_format($costCents / 100, 2, '.', '');
            $formId = 'bressol_pos_market_' . $marketId;

            echo '<tr>';
            echo '<td><input type="text" name="market_name" form="' . esc_attr($formId) . '" value="' . esc_attr($name) . '" required /></td>';
            echo '<td><label><input type="checkbox" name="market_active" form="' . esc_attr($formId) . '" value="1" ' . checked($active, true, false) . ' /> Activo</label></td>';
            echo '<td><input type="number" step="0.01" min="0" name="market_default_cost" form="' . esc_attr($formId) . '" value="' . esc_attr($costEuros) . '" /></td>';
            echo '<td>';
            echo '<form method="post" id="' . esc_attr($formId) . '" style="display:inline-block;">';
            wp_nonce_field('bressol_pos_market_save');
            echo '<input type="hidden" name="market_id" value="' . esc_attr($marketId) . '" />';
            echo '<button type="submit" name="bressol_pos_market_save_submit" class="button">Guardar</button> ';
            echo '</form>';

            echo '<form method="post" style="display:inline-block;margin-left:6px;">';
            wp_nonce_field('bressol_pos_market_toggle');
            echo '<input type="hidden" name="market_id" value="' . esc_attr($marketId) . '" />';
            echo '<input type="hidden" name="market_active" value="' . ($active ? '0' : '1') . '" />';
            echo '<button type="submit" name="bressol_pos_market_toggle_submit" class="button">';
            echo $active ? 'Desactivar' : 'Activar';
            echo '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function renderReportsPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $filters = $this->get_report_filters();
        $this->handle_report_export($filters);

        $limit = 20;
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $limit;

        $queryArgs = $this->build_report_query_args($filters, $limit, $offset, true);
        $results = wc_get_orders($queryArgs);
        $orders = is_object($results) && property_exists($results, 'orders') ? $results->orders : [];
        $total = is_object($results) && property_exists($results, 'total') ? (int) $results->total : 0;

        $metrics = $this->compute_report_metrics($filters);
        $markets = $this->settings->get_markets();

        echo '<div class="wrap bressol-pos">';
        echo '<h1>POS - Reportes</h1>';

        echo '<form method="get" style="margin:16px 0;">';
        echo '<input type="hidden" name="page" value="bressol-pos-reports" />';
        echo '<label>Mercado ';
        echo '<select name="market_id">';
        echo '<option value="">Todos</option>';
        foreach ($markets as $market) {
            $marketId = (string) ($market['id'] ?? '');
            $marketName = (string) ($market['name'] ?? '');
            echo '<option value="' . esc_attr($marketId) . '" ' . selected($filters['market_id'], $marketId, false) . '>'
                . esc_html($marketName) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>Desde <input type="date" name="after" value="' . esc_attr($filters['after'] ?? '') . '" /></label> ';
        echo '<label>Hasta <input type="date" name="before" value="' . esc_attr($filters['before'] ?? '') . '" /></label> ';
        echo '<button class="button">Filtrar</button>';
        echo '</form>';

        echo '<h2>Métricas</h2>';
        echo '<table class="widefat striped" style="max-width:820px;">';
        echo '<tbody>';
        echo '<tr><th>Total ventas (gross)</th><td>' . esc_html(number_format($metrics['gross'], 2)) . '</td></tr>';
        echo '<tr><th>Total impuestos</th><td>' . esc_html(number_format($metrics['tax'], 2)) . '</td></tr>';
        echo '<tr><th>Total neto (estimado)</th><td>' . esc_html(number_format($metrics['net'], 2)) . '</td></tr>';
        echo '<tr><th>Total envíos</th><td>' . esc_html(number_format($metrics['shipping'], 2)) . '</td></tr>';
        echo '<tr><th>Total coste mercado</th><td>' . esc_html(number_format($metrics['market_cost'], 2)) . '</td></tr>';
        echo '<tr><th>Beneficio estimado</th><td>' . esc_html(number_format($metrics['profit'], 2)) . '</td></tr>';
        echo '<tr><th>Nº pedidos POS</th><td>' . esc_html((string) $metrics['count']) . '</td></tr>';
        echo '</tbody></table>';
        echo '<p class="description">Beneficio estimado = gross - tax - market_cost (simplificación contable).</p>';

        echo '<h2>Pedidos POS</h2>';
        echo '<form method="post" style="margin:12px 0;">';
        wp_nonce_field('bressol_pos_reports_export');
        echo '<input type="hidden" name="market_id" value="' . esc_attr($filters['market_id']) . '" />';
        echo '<input type="hidden" name="after" value="' . esc_attr($filters['after'] ?? '') . '" />';
        echo '<input type="hidden" name="before" value="' . esc_attr($filters['before'] ?? '') . '" />';
        echo '<input type="hidden" name="limit" value="' . esc_attr((string) $limit) . '" />';
        echo '<input type="hidden" name="offset" value="' . esc_attr((string) $offset) . '" />';
        echo '<button type="submit" name="bressol_pos_export_submit" class="button">Exportar CSV (página actual)</button>';
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>ID</th><th>Fecha</th><th>Total</th><th>Tax</th><th>Market</th><th>Coste mercado</th><th>Puntos</th><th>Redención</th></tr></thead>';
        echo '<tbody>';
        if (empty($orders)) {
            echo '<tr><td colspan="8">No hay pedidos POS.</td></tr>';
        }
        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }
            $marketName = (string) $order->get_meta('_bressol_pos_market_name');
            $marketCostCents = (int) $order->get_meta('_bressol_pos_market_cost_cents');
            $pointsRedeemed = (int) $order->get_meta('_bressol_pos_points_redeemed');
            $redemptionValue = (int) $order->get_meta('_bressol_pos_redemption_value_cents');
            $date = $order->get_date_created();
            $dateText = $date ? $date->date('Y-m-d H:i:s') : '';

            echo '<tr>';
            echo '<td>' . esc_html((string) $order->get_id()) . '</td>';
            echo '<td>' . esc_html($dateText) . '</td>';
            echo '<td>' . esc_html(number_format((float) $order->get_total(), 2)) . '</td>';
            echo '<td>' . esc_html(number_format((float) $order->get_total_tax(), 2)) . '</td>';
            echo '<td>' . esc_html($marketName) . '</td>';
            echo '<td>' . esc_html(number_format($marketCostCents / 100, 2)) . '</td>';
            echo '<td>' . esc_html((string) $pointsRedeemed) . '</td>';
            echo '<td>' . esc_html(number_format($redemptionValue / 100, 2)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        $this->render_report_pagination($paged, $limit, $total, $filters);

        echo '</div>';
    }

    private function get_capability(): string
    {
        return class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
    }

    private function current_user_can(): bool
    {
        return current_user_can($this->get_capability());
    }

    private function handle_settings_post(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['bressol_pos_settings_submit'])) {
            return;
        }

        if (!$this->current_user_can()) {
            return;
        }

        check_admin_referer('bressol_pos_settings');

        $payload = [
            'order_status_default' => isset($_POST['order_status_default']) ? sanitize_text_field(wp_unslash($_POST['order_status_default'])) : null,
            'points_value_cents' => isset($_POST['points_value_cents']) ? (int) wp_unslash($_POST['points_value_cents']) : null,
            'min_redemption_points' => isset($_POST['min_redemption_points']) ? (int) wp_unslash($_POST['min_redemption_points']) : null,
            'max_redemption_percent_of_order' => isset($_POST['max_redemption_percent_of_order']) ? (int) wp_unslash($_POST['max_redemption_percent_of_order']) : null,
        ];

        $this->settings->update_settings($payload);
        add_settings_error('bressol_pos', 'pos_settings_saved', 'Ajustes POS guardados.', 'updated');
        $this->log_settings_update(['action' => 'settings_updated']);
    }

    private function handle_markets_post(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!$this->current_user_can()) {
            return;
        }

        if (isset($_POST['bressol_pos_market_add_submit'])) {
            check_admin_referer('bressol_pos_market_add');

            $name = isset($_POST['market_name']) ? sanitize_text_field(wp_unslash($_POST['market_name'])) : '';
            if ($name === '') {
                add_settings_error('bressol_pos', 'pos_market_name_required', 'Nombre de mercado obligatorio.', 'error');
                return;
            }

            $active = !empty($_POST['market_active']);
            $rawCost = isset($_POST['market_default_cost']) ? wp_unslash($_POST['market_default_cost']) : '0';
            $defaultCostCents = $this->parse_cost_to_cents($rawCost);

            $marketId = $this->settings->add_market($name, $active, $defaultCostCents);
            add_settings_error('bressol_pos', 'pos_market_added', 'Mercado añadido.', 'updated');
            $this->log_settings_update(['action' => 'market_added', 'market_id' => $marketId]);
        }

        if (isset($_POST['bressol_pos_market_save_submit'])) {
            check_admin_referer('bressol_pos_market_save');

            $marketId = isset($_POST['market_id']) ? sanitize_key(wp_unslash($_POST['market_id'])) : '';
            $name = isset($_POST['market_name']) ? sanitize_text_field(wp_unslash($_POST['market_name'])) : '';
            if ($marketId === '' || $name === '') {
                add_settings_error('bressol_pos', 'pos_market_invalid', 'Mercado inválido.', 'error');
                return;
            }

            $active = !empty($_POST['market_active']);
            $rawCost = isset($_POST['market_default_cost']) ? wp_unslash($_POST['market_default_cost']) : '0';
            $defaultCostCents = $this->parse_cost_to_cents($rawCost);

            if ($this->settings->update_market($marketId, $name, $active, $defaultCostCents)) {
                add_settings_error('bressol_pos', 'pos_market_saved', 'Mercado actualizado.', 'updated');
                $this->log_settings_update(['action' => 'market_updated', 'market_id' => $marketId]);
            } else {
                add_settings_error('bressol_pos', 'pos_market_not_found', 'Mercado no encontrado.', 'error');
            }
        }

        if (isset($_POST['bressol_pos_market_toggle_submit'])) {
            check_admin_referer('bressol_pos_market_toggle');

            $marketId = isset($_POST['market_id']) ? sanitize_key(wp_unslash($_POST['market_id'])) : '';
            if ($marketId === '') {
                add_settings_error('bressol_pos', 'pos_market_invalid', 'Mercado inválido.', 'error');
                return;
            }

            $active = !empty($_POST['market_active']);
            if ($this->settings->set_market_active($marketId, $active)) {
                add_settings_error('bressol_pos', 'pos_market_toggled', 'Mercado actualizado.', 'updated');
                $this->log_settings_update(['action' => 'market_toggled', 'market_id' => $marketId, 'active' => $active]);
            } else {
                add_settings_error('bressol_pos', 'pos_market_not_found', 'Mercado no encontrado.', 'error');
            }
        }
    }

    private function parse_cost_to_cents($value): int
    {
        $raw = is_string($value) ? $value : (string) $value;
        $raw = str_replace(',', '.', $raw);
        $float = (float) $raw;
        $cents = (int) round($float * 100);

        return max(0, $cents);
    }

    /** @param array<string, mixed> $context */
    private function log_settings_update(array $context): void
    {
        if (!class_exists(AuditLogger::class)) {
            return;
        }

        $logger = new AuditLogger();
        $logger->log('pos_settings_updated', 'pos_settings', null, get_current_user_id(), $context);
    }

    /** @return array<string, string> */
    private function get_report_filters(): array
    {
        $marketId = isset($_GET['market_id']) ? sanitize_text_field(wp_unslash($_GET['market_id'])) : '';
        $after = isset($_GET['after']) ? sanitize_text_field(wp_unslash($_GET['after'])) : '';
        $before = isset($_GET['before']) ? sanitize_text_field(wp_unslash($_GET['before'])) : '';

        return [
            'market_id' => $marketId,
            'after' => $after,
            'before' => $before,
        ];
    }

    /** @param array<string, string> $filters */
    private function build_report_query_args(array $filters, int $limit, int $offset, bool $paginate): array
    {
        $metaQuery = [
            [
                'key' => '_bressol_pos_channel',
                'value' => 'pos',
                'compare' => '=',
            ],
        ];

        if (!empty($filters['market_id'])) {
            $metaQuery[] = [
                'key' => '_bressol_pos_market_id',
                'value' => $filters['market_id'],
                'compare' => '=',
            ];
        }

        $args = [
            'status' => array_keys(wc_get_order_statuses()),
            'limit' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
            'paginate' => $paginate,
            'meta_query' => $metaQuery,
        ];

        if (!empty($filters['after']) || !empty($filters['before'])) {
            $args['date_created'] = [
                'after' => $filters['after'] ?: null,
                'before' => $filters['before'] ?: null,
                'inclusive' => true,
            ];
        }

        return $args;
    }

    /** @param array<string, string> $filters
     *  @return array<string, float|int>
     */
    private function compute_report_metrics(array $filters): array
    {
        $queryArgs = $this->build_report_query_args($filters, -1, 0, false);
        $queryArgs['return'] = 'ids';
        $orders = wc_get_orders($queryArgs);

        $gross = 0.0;
        $tax = 0.0;
        $shipping = 0.0;
        $marketCost = 0.0;
        $count = 0;

        foreach ($orders as $orderId) {
            $order = wc_get_order((int) $orderId);
            if (!$order) {
                continue;
            }
            $count++;
            $gross += (float) $order->get_total();
            $tax += (float) $order->get_total_tax();
            $shipping += (float) $order->get_shipping_total();
            $marketCostCents = (int) $order->get_meta('_bressol_pos_market_cost_cents');
            $marketCost += $marketCostCents / 100;
        }

        $net = $gross - $tax - $shipping;
        $profit = $gross - $tax - $marketCost;

        return [
            'gross' => $gross,
            'tax' => $tax,
            'net' => $net,
            'shipping' => $shipping,
            'market_cost' => $marketCost,
            'profit' => $profit,
            'count' => $count,
        ];
    }

    /** @param array<string, string> $filters */
    private function render_report_pagination(int $paged, int $limit, int $total, array $filters): void
    {
        $totalPages = (int) ceil($total / $limit);
        if ($totalPages <= 1) {
            return;
        }

        $paged = min(max(1, $paged), $totalPages);
        $baseArgs = [
            'page' => 'bressol-pos-reports',
            'market_id' => $filters['market_id'],
            'after' => $filters['after'],
            'before' => $filters['before'],
        ];

        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo '<span class="displaying-num">' . esc_html($paged . ' / ' . $totalPages) . '</span> ';

        $this->render_report_page_link($paged <= 1, 1, '« Primera', $baseArgs);
        $this->render_report_page_link($paged <= 1, $paged - 1, '‹ Anterior', $baseArgs);
        $this->render_report_page_link($paged >= $totalPages, $paged + 1, 'Siguiente ›', $baseArgs);
        $this->render_report_page_link($paged >= $totalPages, $totalPages, 'Última »', $baseArgs);

        echo '</div></div>';
    }

    /** @param array<string, string> $baseArgs */
    private function render_report_page_link(bool $disabled, int $page, string $label, array $baseArgs): void
    {
        if ($disabled) {
            echo '<span class="tablenav-pages-navspan" aria-hidden="true">' . esc_html($label) . '</span> ';
            return;
        }

        $url = add_query_arg(array_merge($baseArgs, ['paged' => $page]), admin_url('admin.php'));
        echo '<a class="button" href="' . esc_url($url) . '">' . esc_html($label) . '</a> ';
    }

    /** @param array<string, string> $filters */
    private function handle_report_export(array $filters): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['bressol_pos_export_submit'])) {
            return;
        }

        if (!$this->current_user_can()) {
            return;
        }

        check_admin_referer('bressol_pos_reports_export');

        $limit = isset($_POST['limit']) ? max(1, (int) wp_unslash($_POST['limit'])) : 20;
        $offset = isset($_POST['offset']) ? max(0, (int) wp_unslash($_POST['offset'])) : 0;
        $filters = [
            'market_id' => isset($_POST['market_id']) ? sanitize_text_field(wp_unslash($_POST['market_id'])) : '',
            'after' => isset($_POST['after']) ? sanitize_text_field(wp_unslash($_POST['after'])) : '',
            'before' => isset($_POST['before']) ? sanitize_text_field(wp_unslash($_POST['before'])) : '',
        ];

        $queryArgs = $this->build_report_query_args($filters, $limit, $offset, false);
        $orders = wc_get_orders($queryArgs);

        nocache_headers();
        header('Content-Type: text/csv; charset=' . get_option('blog_charset'));
        header('Content-Disposition: attachment; filename=pos-report.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['order_id', 'date', 'total', 'tax', 'market_name', 'market_cost', 'points_redeemed', 'redemption_value']);

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }
            $date = $order->get_date_created();
            $dateText = $date ? $date->date('Y-m-d H:i:s') : '';
            $marketCostCents = (int) $order->get_meta('_bressol_pos_market_cost_cents');
            $pointsRedeemed = (int) $order->get_meta('_bressol_pos_points_redeemed');
            $redemptionValue = (int) $order->get_meta('_bressol_pos_redemption_value_cents');

            fputcsv($output, [
                $order->get_id(),
                $dateText,
                number_format((float) $order->get_total(), 2, '.', ''),
                number_format((float) $order->get_total_tax(), 2, '.', ''),
                (string) $order->get_meta('_bressol_pos_market_name'),
                number_format($marketCostCents / 100, 2, '.', ''),
                $pointsRedeemed,
                number_format($redemptionValue / 100, 2, '.', ''),
            ]);
        }

        fclose($output);
        exit;
    }

    /** @return array<string, mixed>|null */
    private function handle_customer_lookup_post(): ?array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        if (!isset($_POST['bressol_pos_customer_search_submit'])) {
            return null;
        }

        if (!$this->current_user_can()) {
            return null;
        }

        check_admin_referer('bressol_pos_customer_lookup');

        $token = isset($_POST['pos_public_id']) ? sanitize_text_field(wp_unslash($_POST['pos_public_id'])) : '';
        if ($token === '') {
            add_settings_error('bressol_pos_customers', 'pos_customer_empty', 'Introduce un token o ID.', 'error');
            return null;
        }

        $payload = $this->lookupService->find_by_public_id($token);
        if ($payload === null && ctype_digit($token)) {
            $payload = $this->lookupService->find_by_customer_id((int) $token);
        }

        if ($payload === null) {
            add_settings_error('bressol_pos_customers', 'pos_customer_not_found', 'Cliente no encontrado.', 'error');
            return null;
        }

        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function handle_customer_regenerate_post(): ?array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }

        if (!isset($_POST['bressol_pos_customer_regenerate_submit'])) {
            return null;
        }

        if (!$this->current_user_can()) {
            return null;
        }

        check_admin_referer('bressol_pos_customer_regenerate');

        $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        if ($customerId <= 0) {
            add_settings_error('bressol_pos_customers', 'pos_customer_invalid', 'Cliente inválido.', 'error');
            return null;
        }

        $token = $this->lookupService->regenerate_public_id($customerId);
        if ($token === null) {
            add_settings_error('bressol_pos_customers', 'pos_customer_regen_failed', 'No se pudo regenerar el ID.', 'error');
            return null;
        }

        add_settings_error('bressol_pos_customers', 'pos_customer_regen_ok', 'ID regenerado.', 'updated');

        return $this->lookupService->find_by_customer_id($customerId);
    }

    private function handle_customer_create_post(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['bressol_pos_customer_create_submit'])) {
            return;
        }

        if (!$this->current_user_can()) {
            return;
        }

        check_admin_referer('bressol_pos_customer_create');

        $payload = [
            'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
            'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
            'email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
            'customer_type' => isset($_POST['customer_type']) ? sanitize_text_field(wp_unslash($_POST['customer_type'])) : 'b2c',
            'loyalty_enabled' => !empty($_POST['loyalty_enabled']),
            'can_receive_marketing' => !empty($_POST['can_receive_marketing']),
        ];

        $result = $this->customerService->create_from_pos($payload);
        if (empty($result['ok'])) {
            add_settings_error('bressol_pos_customers', $result['code'] ?? 'pos_customer_create_failed', $result['error'] ?? 'No se pudo crear el cliente.', 'error');
            return;
        }

        $customerId = (int) ($result['customer_id'] ?? 0);
        if ($customerId > 0) {
            $this->lookupService->ensure_public_id($customerId, false);
        }

        add_settings_error('bressol_pos_customers', 'pos_customer_created', 'Cliente creado.', 'updated');

        if (!empty($result['warning'])) {
            add_settings_error('bressol_pos_customers', $result['warning_code'] ?? 'pos_customer_warning', (string) $result['warning'], 'warning');
        }
    }
}
