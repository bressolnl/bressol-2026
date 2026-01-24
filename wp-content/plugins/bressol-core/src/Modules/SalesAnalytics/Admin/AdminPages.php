<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Admin;

use Bressol\Modules\SalesAnalytics\Repositories\OrderQuery;
use Bressol\Modules\SalesAnalytics\Services\Capabilities;
use Bressol\Modules\SalesAnalytics\Services\CacheService;
use Bressol\Modules\SalesAnalytics\Services\MetricsExtractor;
use Bressol\Modules\SalesAnalytics\Services\Settings;
use Bressol\Modules\SalesAnalytics\Services\TaxBreakdownService;
use Bressol\Modules\Pos\Services\PosSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private Settings $settings;
    private Capabilities $capabilities;
    private CacheService $cacheService;
    private OrderQuery $orderQuery;
    private TaxBreakdownService $taxBreakdownService;
    private MetricsExtractor $metricsExtractor;

    public function __construct(
        Settings $settings,
        Capabilities $capabilities,
        CacheService $cacheService
    ) {
        $this->settings = $settings;
        $this->capabilities = $capabilities;
        $this->cacheService = $cacheService;
        $this->orderQuery = new OrderQuery();
        $this->taxBreakdownService = new TaxBreakdownService();
        $this->metricsExtractor = new MetricsExtractor();
    }

    public function registerMenus(): void
    {
        $capability = $this->capabilities->get_base_capability();

        add_menu_page(
            'Bressol',
            'Bressol',
            $capability,
            'bressol',
            [$this, 'renderDashboardPage'],
            'dashicons-chart-bar',
            56
        );

        add_submenu_page(
            'bressol',
            'Sales Analytics',
            'Sales Analytics',
            $capability,
            'bressol-sales-analytics',
            [$this, 'renderDashboardPage']
        );

        add_submenu_page(
            'bressol',
            'Sales Analytics - Exports',
            'Sales Analytics - Exports',
            $capability,
            'bressol-sales-analytics-exports',
            [$this, 'renderExportsPage']
        );

        add_submenu_page(
            'bressol',
            'Sales Analytics - Settings',
            'Sales Analytics - Settings',
            'manage_options',
            'bressol-sales-analytics-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (!in_array($page, ['bressol-sales-analytics', 'bressol-sales-analytics-exports', 'bressol-sales-analytics-settings'], true)) {
            return;
        }

        // Placeholder: sin assets en Paso 2.
    }

    public function renderDashboardPage(): void
    {
        if (!$this->capabilities->current_user_can_view()) {
            wp_die('No autorizado.');
        }

        $filters = $this->get_filters_from_request($_GET);
        $markets = $this->get_pos_markets();

        $cached = $this->cacheService->get('dashboard', $filters);
        $cacheHit = is_array($cached);
        $dashboard = $cacheHit ? $cached : $this->compute_dashboard_payload($filters);
        if (!isset($dashboard['too_large'])) {
            $dashboard['too_large'] = false;
        }

        if (!$cacheHit && !$dashboard['too_large']) {
            $this->cacheService->set('dashboard', $filters, $dashboard, 10 * MINUTE_IN_SECONDS);
        }

        echo '<div class="wrap">';
        echo '<h1>Sales Analytics - Dashboard</h1>';
        echo $this->render_filters_form('get', $filters, $markets);

        if (!$dashboard['too_large']) {
            $meta = $dashboard['meta'] ?? [];
            $computedAt = isset($meta['computed_at']) ? (string) $meta['computed_at'] : '';
            $ordersCount = isset($meta['orders_count']) ? (int) $meta['orders_count'] : 0;
            $source = $cacheHit ? 'cache' : 'recalculado';
            if ($computedAt !== '') {
                echo '<p class="description">Último cálculo: ' . esc_html($computedAt) . ' (' . esc_html($source) . ') — ' . esc_html((string) $ordersCount) . ' pedidos.</p>';
            }
        }

        if ($dashboard['too_large']) {
            echo '<p class="notice notice-warning" style="padding:8px 12px;">';
            echo 'Rango demasiado grande para cálculo de desglose fiscal. Acota fechas o usa export.';
            echo '</p>';
            echo '</div>';
            return;
        }

        $totals = $dashboard['totals'];
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">';
        echo $this->render_metric_card('Gross', $totals['total_incl_tax_cents']);
        echo $this->render_metric_card('Net', $totals['net_sales_excl_tax_cents']);
        echo $this->render_metric_card('Tax total', $totals['tax_total_cents']);
        echo $this->render_metric_card('Discounts', $totals['discount_incl_tax_cents']);
        echo $this->render_metric_card('Refunds', $totals['refunds_incl_tax_cents']);
        echo $this->render_metric_card('Profit (est.)', $totals['profit_estimated_excl_tax_cents']);
        echo '</div>';

        $taxBreakdown = $dashboard['tax_breakdown'];
        $discrepancies = $dashboard['discrepancies'];
        $discrepanciesTotal = isset($dashboard['discrepancies_total']) ? (int) $dashboard['discrepancies_total'] : count($discrepancies);
        echo '<h2 style="margin-top:24px;">Desglose fiscal por tipo</h2>';

        if ($taxBreakdown === []) {
            echo '<p>No hay datos para el rango seleccionado.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:820px;">';
        echo '<thead><tr><th>Rate %</th><th>Base imponible (€)</th><th>Impuesto (€)</th><th>Pedidos</th></tr></thead>';
        echo '<tbody>';
        foreach ($taxBreakdown as $rate => $row) {
            echo '<tr>';
            echo '<td>' . esc_html($rate) . '</td>';
            echo '<td>' . esc_html($this->format_euros($row['taxable_base_cents'])) . '</td>';
            echo '<td>' . esc_html($this->format_euros($row['tax_cents'])) . '</td>';
            echo '<td>' . esc_html((string) $row['orders_count']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        if ($discrepancies !== []) {
            $invalidCount = count($discrepancies);
            echo '<p class="notice notice-warning" style="padding:8px 12px;margin-top:12px;">';
            echo 'Validación fiscal: ' . esc_html((string) $discrepanciesTotal) . ' pedidos con discrepancias.';
            if ($discrepanciesTotal > $invalidCount) {
                echo ' Mostrando ' . esc_html((string) $invalidCount) . '.';
            }
            echo '</p>';
            echo '<div style="margin-top:8px;">';
            echo '<strong>Detalle:</strong>';
            echo '<ul style="margin:8px 0 0 16px;">';
            foreach ($discrepancies as $row) {
                $orderId = (int) $row['order_id'];
                $link = admin_url('post.php?post=' . $orderId . '&action=edit');
                echo '<li><a href="' . esc_url($link) . '">Order #' . esc_html((string) $orderId) . '</a> — diff ' . esc_html((string) $row['diff_tax_cents']) . ' cents</li>';
            }
            echo '</ul>';
            echo '</div>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:12px;">';
            wp_nonce_field('bressol_sales_export_discrepancies_nonce');
            echo '<input type="hidden" name="action" value="bressol_sales_export_discrepancies" />';
            echo $this->render_filters_hidden_inputs($filters);
            echo '<button type="submit" class="button">Descargar CSV de discrepancias</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    public function renderExportsPage(): void
    {
        if (!$this->capabilities->current_user_can_view()) {
            wp_die('No autorizado.');
        }

        $filters = $this->get_filters_from_request($_GET);
        $markets = $this->get_pos_markets();
        $canExportPii = $this->capabilities->current_user_can_export_pii();
        $piiEnabled = $this->settings->is_pii_export_enabled();
        $salesError = isset($_GET['sales_error']) ? sanitize_text_field(wp_unslash($_GET['sales_error'])) : '';

        echo '<div class="wrap">';
        echo '<h1>Sales Analytics - Exports</h1>';
        echo $this->render_filters_form('get', $filters, $markets);

        echo '<h2>Exportes</h2>';
        if ($salesError !== '') {
            echo '<p class="notice notice-error" style="padding:8px 12px;">';
            echo esc_html($this->format_export_error($salesError));
            echo '</p>';
        }
        if ($canExportPii) {
            if (!$piiEnabled) {
                $settingsUrl = admin_url('admin.php?page=bressol-sales-analytics-settings');
                echo '<p class="notice notice-warning" style="padding:8px 12px;">Deshabilitado en Settings. ';
                echo '<a href="' . esc_url($settingsUrl) . '">Configurar</a>.';
                echo '</p>';
            }
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        wp_nonce_field('bressol_sales_export_orders_nonce');
        echo '<input type="hidden" name="action" value="bressol_sales_export_orders" />';
        echo $this->render_filters_hidden_inputs($filters);
        echo $this->render_pii_toggle($canExportPii, $piiEnabled);
        echo '<button type="submit" class="button button-primary">Exportar pedidos (CSV)</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        wp_nonce_field('bressol_sales_export_daily_nonce');
        echo '<input type="hidden" name="action" value="bressol_sales_export_daily" />';
        echo $this->render_filters_hidden_inputs($filters);
        echo $this->render_pii_toggle($canExportPii, $piiEnabled);
        echo '<button type="submit" class="button">Exportar agregado diario (CSV)</button>';
        echo '</form>';

        echo '</div>';
    }

    /** @param array<string, mixed> $input */
    private function get_filters_from_request(array $input): array
    {
        $from = isset($input['date_from']) ? sanitize_text_field(wp_unslash($input['date_from'])) : '';
        $to = isset($input['date_to']) ? sanitize_text_field(wp_unslash($input['date_to'])) : '';
        $channel = isset($input['channel']) ? sanitize_text_field(wp_unslash($input['channel'])) : 'all';
        $marketId = isset($input['market_id']) ? sanitize_text_field(wp_unslash($input['market_id'])) : '';

        return [
            'date_from' => $this->sanitize_date($from),
            'date_to' => $this->sanitize_date($to),
            'channel' => $this->sanitize_channel($channel),
            'market_id' => $marketId,
        ];
    }

    private function sanitize_date(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    private function sanitize_channel(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || $value === 'all') {
            return 'all';
        }

        return in_array($value, ['web', 'pos'], true) ? $value : 'all';
    }

    /** @param array<string, mixed> $filters
     *  @return array{too_large:bool, totals:array<string, int>, tax_breakdown:array<string, array{tax_cents:int, taxable_base_cents:int, orders_count:int}>, discrepancies:array<int, array{order_id:int, diff_tax_cents:int}>, meta:array<string, mixed>}
     */
    private function compute_dashboard_payload(array $filters): array
    {
        $aggregate = [];
        $discrepancies = [];
        $discrepanciesTotal = 0;
        $totalOrders = 0;
        $maxOrders = 2000;
        $page = 1;
        $limit = 200;
        $maxDiscrepancies = 50;
        $totals = [
            'orders_count' => 0,
            'total_incl_tax_cents' => 0,
            'tax_total_cents' => 0,
            'total_excl_tax_cents' => 0,
            'shipping_incl_tax_cents' => 0,
            'discount_incl_tax_cents' => 0,
            'refunds_incl_tax_cents' => 0,
            'market_cost_cents' => 0,
            'net_sales_excl_tax_cents' => 0,
            'profit_estimated_excl_tax_cents' => 0,
        ];

        do {
            $orderIds = $this->orderQuery->find_order_ids($filters, $limit, $page);
            if ($orderIds === []) {
                break;
            }

            foreach ($orderIds as $orderId) {
                $totalOrders++;
                if ($totalOrders > $maxOrders) {
                    return [
                        'too_large' => true,
                        'totals' => $totals,
                        'tax_breakdown' => [],
                        'discrepancies' => [],
                        'meta' => [],
                    ];
                }

                $order = wc_get_order($orderId);
                if (!$order instanceof \WC_Order) {
                    continue;
                }

                $metrics = $this->metricsExtractor->extract($order);
                $totals['orders_count']++;
                $totals['total_incl_tax_cents'] += $metrics['total_incl_tax_cents'];
                $totals['tax_total_cents'] += $metrics['tax_total_cents'];
                $totals['total_excl_tax_cents'] += $metrics['total_excl_tax_cents'];
                $totals['shipping_incl_tax_cents'] += $metrics['shipping_incl_tax_cents'];
                $totals['discount_incl_tax_cents'] += $metrics['discount_incl_tax_cents'];
                $totals['refunds_incl_tax_cents'] += $metrics['refunds_incl_tax_cents'];
                $totals['market_cost_cents'] += $metrics['market_cost_cents'];
                $totals['net_sales_excl_tax_cents'] += $metrics['net_sales_excl_tax_cents'];
                $totals['profit_estimated_excl_tax_cents'] += $metrics['profit_estimated_excl_tax_cents'];

                $breakdown = $this->taxBreakdownService->breakdown_for_order($order);
                $touchedRates = [];
                foreach ($breakdown as $rate => $row) {
                    if (!isset($aggregate[$rate])) {
                        $aggregate[$rate] = [
                            'tax_cents' => 0,
                            'taxable_base_cents' => 0,
                            'orders_count' => 0,
                        ];
                    }

                    $aggregate[$rate]['tax_cents'] += (int) $row['tax_cents'];
                    $aggregate[$rate]['taxable_base_cents'] += (int) $row['taxable_base_cents'];
                    $touchedRates[$rate] = true;
                }

                foreach (array_keys($touchedRates) as $rate) {
                    $aggregate[$rate]['orders_count']++;
                }

                $validation = $this->taxBreakdownService->validate_order($order, $breakdown);
                if (!$validation['ok']) {
                    $discrepanciesTotal++;
                    if (count($discrepancies) < $maxDiscrepancies) {
                        $discrepancies[] = [
                            'order_id' => (int) $order->get_id(),
                            'diff_tax_cents' => (int) $validation['diff_tax_cents'],
                        ];
                    }
                }
            }

            $page++;
        } while (true);

        uksort($aggregate, static function (string $a, string $b): int {
            return (float) $a <=> (float) $b;
        });

        return [
            'too_large' => false,
            'totals' => $totals,
            'tax_breakdown' => $aggregate,
            'discrepancies' => $discrepancies,
            'discrepancies_total' => $discrepanciesTotal,
            'meta' => [
                'orders_count' => $totals['orders_count'],
                'computed_at' => current_time('mysql'),
            ],
        ];
    }

    private function format_euros(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function format_export_error(string $code): string
    {
        switch ($code) {
            case 'invalid_nonce':
                return 'Nonce inválido. Intenta de nuevo.';
            case 'forbidden':
                return 'No autorizado para exportar.';
            case 'pii_forbidden':
                return 'Export con PII no permitido.';
            case 'rate_limited':
                return 'Demasiadas exportaciones seguidas. Espera unos segundos e inténtalo otra vez.';
            case 'range_too_large':
                return 'Rango demasiado grande para exportar. Acota fechas e inténtalo de nuevo.';
            default:
                return 'No se pudo completar la exportación.';
        }
    }

    /** @param array<string, mixed> $filters */
    private function render_filters_form(string $method, array $filters, array $markets): string
    {
        $html = '<form method="' . esc_attr($method) . '" style="margin:16px 0;">';
        $html .= '<input type="hidden" name="page" value="' . esc_attr($this->get_current_page_slug()) . '" />';
        $html .= '<label>Desde <input type="date" name="date_from" value="' . esc_attr((string) $filters['date_from']) . '" /></label> ';
        $html .= '<label>Hasta <input type="date" name="date_to" value="' . esc_attr((string) $filters['date_to']) . '" /></label> ';
        $html .= '<label>Canal ';
        $html .= '<select name="channel">';
        $html .= '<option value="all" ' . selected((string) $filters['channel'], 'all', false) . '>Todos</option>';
        $html .= '<option value="web" ' . selected((string) $filters['channel'], 'web', false) . '>Web</option>';
        $html .= '<option value="pos" ' . selected((string) $filters['channel'], 'pos', false) . '>POS</option>';
        $html .= '</select></label> ';

        $html .= '<label>Mercado ';
        $html .= '<select name="market_id">';
        $html .= '<option value="">Todos</option>';
        foreach ($markets as $market) {
            $marketId = (string) ($market['id'] ?? '');
            $marketName = (string) ($market['name'] ?? '');
            $html .= '<option value="' . esc_attr($marketId) . '" ' . selected((string) $filters['market_id'], $marketId, false) . '>'
                . esc_html($marketName) . '</option>';
        }
        $html .= '</select></label> ';
        $html .= '<button class="button">Filtrar</button>';
        $html .= '</form>';

        return $html;
    }

    /** @param array<string, mixed> $filters */
    private function render_filters_hidden_inputs(array $filters): string
    {
        $html = '';
        $html .= '<input type="hidden" name="date_from" value="' . esc_attr((string) $filters['date_from']) . '" />';
        $html .= '<input type="hidden" name="date_to" value="' . esc_attr((string) $filters['date_to']) . '" />';
        $html .= '<input type="hidden" name="channel" value="' . esc_attr((string) $filters['channel']) . '" />';
        $html .= '<input type="hidden" name="market_id" value="' . esc_attr((string) $filters['market_id']) . '" />';

        return $html;
    }

    private function render_pii_toggle(bool $canExportPii, bool $piiEnabled): string
    {
        if (!$canExportPii) {
            return '';
        }

        $disabled = $piiEnabled ? '' : 'disabled';
        $label = $piiEnabled ? 'Incluir PII' : 'Incluir PII (deshabilitado en Settings)';

        return '<label style="margin-right:12px;"><input type="checkbox" name="include_pii" value="1" ' . $disabled . ' /> ' . esc_html($label) . '</label>';
    }

    private function render_metric_card(string $title, int $cents): string
    {
        return '<div style="padding:12px;border:1px solid #dcdcde;background:#fff;">'
            . '<strong>' . esc_html($title) . '</strong>'
            . '<div style="font-size:20px;margin-top:6px;">' . esc_html($this->format_euros($cents)) . '</div>'
            . '</div>';
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        $this->handleSettingsPost();
        $settings = $this->settings->get();

        echo '<div class="wrap">';
        echo '<h1>Sales Analytics - Settings</h1>';
        settings_errors('bressol_sales_analytics');

        echo '<form method="post">';
        wp_nonce_field('bressol_sales_analytics_settings');
        echo '<table class="form-table">';
        echo '<tr><th>Permitir exportes con PII</th><td>';
        echo '<label><input type="checkbox" name="export_pii_enabled" value="1" ' . checked(!empty($settings['export_pii_enabled']), true, false) . ' />';
        echo ' Activar exportes con PII</label>';
        echo '<p class="description">';
        echo 'Por defecto está desactivado. Si lo activas, se permitirá incluir email, nombre y país en exports para tareas contables/soporte. ';
        echo 'Los exports se auditan sin guardar PII.';
        echo '</p>';
        echo '</td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" name="bressol_sales_analytics_settings_submit" class="button button-primary">Guardar</button></p>';
        echo '</form>';
        echo '</div>';
    }

    private function handleSettingsPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['bressol_sales_analytics_settings_submit'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('bressol_sales_analytics_settings');

        $enabled = !empty($_POST['export_pii_enabled']);
        $this->settings->update([
            'export_pii_enabled' => $enabled,
        ]);

        add_settings_error('bressol_sales_analytics', 'bressol_sales_analytics_settings_saved', 'Ajustes guardados.', 'updated');
    }

    private function get_current_page_slug(): string
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === 'bressol-sales-analytics-exports') {
            return 'bressol-sales-analytics-exports';
        }

        return 'bressol-sales-analytics';
    }

    /** @return array<int, array<string, mixed>> */
    private function get_pos_markets(): array
    {
        if (!class_exists(PosSettings::class)) {
            return [];
        }

        $settings = new PosSettings();
        $markets = $settings->get_markets();

        return is_array($markets) ? $markets : [];
    }
}
