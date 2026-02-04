<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Admin;

use Bressol\Modules\Crm\Services\Capabilities;
use Bressol\Modules\Crm\Services\CustomerSearchService;
use Bressol\Modules\Crm\Services\Settings;

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
            'CRM',
            'CRM',
            $capability,
            'bressol_crm',
            [$this, 'renderHome']
        );

        add_submenu_page(
            'bressol',
            'CRM - Configuración',
            'Configuración',
            $capability,
            'bressol_crm_settings',
            [$this, 'renderSettings']
        );

        add_submenu_page(
            'bressol',
            'CRM - Clientes',
            'Clientes',
            $capability,
            'bressol_crm_customers',
            [$this, 'renderCustomers']
        );

        add_submenu_page(
            'bressol',
            'Diagnóstico CRM',
            'Diagnóstico',
            $capability,
            'bressol_crm_diagnostics',
            [DiagnosticsPage::class, 'render']
        );
    }

    public function renderHome(): void
    {
        $this->ensureAccess();
               global $wpdb;
        $customersTable = $wpdb->prefix . 'bressol_crm_customers';
        $ledgerTable = $wpdb->prefix . 'bressol_crm_points_ledger';

        $totalCustomers = null;
        $loyaltyCustomers = null;
        $totalPoints = null;
        $pointsIssuedToday = null;

        if ($this->table_exists($customersTable)) {
            $totalCustomers = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$customersTable}");
            $loyaltyCustomers = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$customersTable} WHERE loyalty_enabled = 1");
        }

        if ($this->table_exists($ledgerTable)) {
            $now = current_time('mysql');
            $totalPoints = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(points), 0)
                     FROM {$ledgerTable}
                     WHERE status = 'active' AND expires_at >= %s",
                    $now
                )
            );
            $todayStart = date('Y-m-d 00:00:00', current_time('timestamp'));
            $pointsIssuedToday = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(points), 0)
                     FROM {$ledgerTable}
                     WHERE points > 0 AND earned_at >= %s",
                    $todayStart
                )
            );
        }

        echo '<div class="wrap">';
        echo '<h1>CRM</h1>';
        echo '<table class="widefat striped" style="max-width:700px;">';
        echo '<tbody>';
        echo '<tr><th>Total clientes</th><td>' . esc_html($totalCustomers !== null ? (string) $totalCustomers : 'N/D') . '</td></tr>';
        echo '<tr><th>Clientes con loyalty</th><td>' . esc_html($loyaltyCustomers !== null ? (string) $loyaltyCustomers : 'N/D') . '</td></tr>';
        echo '<tr><th>Balance total puntos</th><td>' . esc_html($totalPoints !== null ? (string) $totalPoints : 'N/D') . '</td></tr>';
        echo '<tr><th>Puntos emitidos hoy</th><td>' . esc_html($pointsIssuedToday !== null ? (string) $pointsIssuedToday : 'N/D') . '</td></tr>';
        echo '</tbody></table>';
        echo '</div>';
    }

    public function renderSettings(): void
    {
        $this->ensureAccess();
        $settings = (new Settings())->get_settings();

        echo '<div class="wrap">';
        echo '<h1>Configuración CRM</h1>';
        echo '<table class="widefat striped" style="max-width:700px;">';
        echo '<tbody>';
        foreach ($settings as $key => $value) {
            echo '<tr><th>' . esc_html((string) $key) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function renderCustomers(): void
    {
        $this->ensureAccess();
        global $wpdb;

        $customersTable = $wpdb->prefix . 'bressol_crm_customers';
        if (!$this->table_exists($customersTable)) {
            $diagnosticsUrl = add_query_arg('page', 'bressol_crm_diagnostics', admin_url('admin.php'));
            echo '<div class="wrap">';
            echo '<h1>Clientes</h1>';
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('CRM no instalado / tablas faltan.', 'bressol-core');
            echo ' <a href="' . esc_url($diagnosticsUrl) . '">' . esc_html__('Ir al diagnóstico CRM', 'bressol-core') . '</a>';
            echo '</p></div>';
            echo '</div>';
            return;
        }

        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $perPage = 20;

        $search = new CustomerSearchService();
        $results = $search->search($query, $page, $perPage);

        $viewId = isset($_GET['view_id']) ? absint($_GET['view_id']) : 0;
        // UX: si estás buscando (pero NO estás en un detalle), evita quedarse “anclado”.
        if ($query !== '' && $viewId === 0) {
            $viewId = 0;
        }
        // no-op: permitimos view_id aunque haya query

        echo '<div class="wrap">';
        echo '<h1>Clientes</h1>';

        echo '<form method="get" style="margin: 12px 0;">';
        echo '<input type="hidden" name="page" value="bressol_crm_customers" />';
        echo '<input type="hidden" name="paged" value="1" />';
        echo '<label for="crm-search-q" class="screen-reader-text">Buscar</label>';
        echo '<input type="search" id="crm-search-q" name="q" value="' . esc_attr($query) . '" placeholder="Buscar por ID, email o nombre" />';
        echo '<input type="submit" class="button" value="Buscar" />';
        echo '</form>';

        if ($viewId > 0) {
            $customer = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, email, first_name, last_name, customer_type, status, loyalty_enabled,
                            can_receive_marketing, can_be_profiled, created_at, updated_at
                     FROM {$customersTable}
                     WHERE id = %d
                     LIMIT 1",
                    $viewId
                )
            );

            if ($customer) {
                echo '<h2>Detalle cliente #' . esc_html((string) $customer->id) . '</h2>';
                echo '<table class="widefat striped" style="max-width:800px;">';
                echo '<tbody>';
                echo '<tr><th>Email</th><td>' . esc_html((string) $customer->email) . '</td></tr>';
                echo '<tr><th>Nombre</th><td>' . esc_html(trim((string) ($customer->first_name ?? '') . ' ' . (string) ($customer->last_name ?? ''))) . '</td></tr>';
                echo '<tr><th>Tipo</th><td>' . esc_html((string) $customer->customer_type) . '</td></tr>';
                echo '<tr><th>Status</th><td>' . esc_html((string) $customer->status) . '</td></tr>';
                echo '<tr><th>Loyalty</th><td>' . esc_html(((int) $customer->loyalty_enabled) === 1 ? 'Sí' : 'No') . '</td></tr>';
                echo '<tr><th>Marketing</th><td>' . esc_html(((int) $customer->can_receive_marketing) === 1 ? 'Sí' : 'No') . '</td></tr>';
                echo '<tr><th>Perfilado</th><td>' . esc_html(((int) $customer->can_be_profiled) === 1 ? 'Sí' : 'No') . '</td></tr>';
                echo '<tr><th>Creado</th><td>' . esc_html((string) $customer->created_at) . '</td></tr>';
                echo '<tr><th>Actualizado</th><td>' . esc_html((string) $customer->updated_at) . '</td></tr>';
                echo '</tbody></table>';

                $ledgerTable = $wpdb->prefix . 'bressol_crm_points_ledger';
                if ($this->table_exists($ledgerTable)) {
                    $balance = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COALESCE(SUM(points), 0)
                             FROM {$ledgerTable}
                             WHERE customer_id = %d
                               AND status = 'active'
                               AND expires_at >= %s",
                            $viewId,
                            current_time('mysql')
                        )
                    );
                    echo '<p><strong>Balance puntos:</strong> ' . esc_html((string) $balance) . '</p>';

                    $ledgerRows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT id, points, status, earned_at, expires_at, created_at
                             FROM {$ledgerTable}
                             WHERE customer_id = %d
                             ORDER BY earned_at DESC, id DESC
                             LIMIT 10",
                            $viewId
                        )
                    );

                    echo '<h3>Últimos movimientos de puntos</h3>';
                    if ($ledgerRows) {
                        echo '<table class="widefat striped" style="max-width:900px;">';
                        echo '<thead><tr><th>ID</th><th>Puntos</th><th>Status</th><th>Earned</th><th>Expires</th><th>Creado</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($ledgerRows as $row) {
                            echo '<tr>';
                            echo '<td>' . esc_html((string) $row->id) . '</td>';
                            echo '<td>' . esc_html((string) $row->points) . '</td>';
                            echo '<td>' . esc_html((string) $row->status) . '</td>';
                            echo '<td>' . esc_html((string) $row->earned_at) . '</td>';
                            echo '<td>' . esc_html((string) $row->expires_at) . '</td>';
                            echo '<td>' . esc_html((string) $row->created_at) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        echo '<p>No hay movimientos registrados.</p>';
                    }
                }
            } else {
                echo '<div class="notice notice-warning"><p>Cliente no encontrado.</p></div>';
            }
        }

        $items = $results['items'];
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Email</th><th>Nombre</th><th>Tipo</th><th>Status</th><th>Loyalty</th><th>Updated</th><th>Acciones</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (!$items) {
            echo '<tr><td colspan="8">Sin resultados.</td></tr>';
        } else {
            foreach ($items as $item) {
                $name = trim((string) ($item['first_name'] ?? '') . ' ' . (string) ($item['last_name'] ?? ''));
                if ($name === '') {
                    $name = '—';
                }
                $viewUrl = add_query_arg(
                    [
                        'page' => 'bressol_crm_customers',
                        'q' => $query,
                        'paged' => $page,
                        'view_id' => $item['id'],
                    ],
                    admin_url('admin.php')
                );
                echo '<tr>';
                echo '<td>' . esc_html((string) $item['id']) . '</td>';
                echo '<td>' . esc_html((string) $item['email']) . '</td>';
                echo '<td>' . esc_html($name) . '</td>';
                echo '<td>' . esc_html((string) $item['customer_type']) . '</td>';
                echo '<td>' . esc_html((string) $item['status']) . '</td>';
                echo '<td>' . esc_html(((int) $item['loyalty_enabled'] === 1) ? 'Sí' : 'No') . '</td>';
                echo '<td>' . esc_html((string) $item['updated_at']) . '</td>';
                echo '<td><a href="' . esc_url($viewUrl) . '">Ver</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        $total = (int) $results['total'];
        $start = $total > 0 ? (($page - 1) * $perPage + 1) : 0;
        $end = $total > 0 ? min($page * $perPage, $total) : 0;

        echo '<p>Mostrando ' . esc_html((string) $start) . '–' . esc_html((string) $end) . ' de ' . esc_html((string) $total) . '</p>';

        $prevPage = $page > 1 ? $page - 1 : null;
        $nextPage = ($page * $perPage) < $total ? $page + 1 : null;

        echo '<div class="tablenav"><div class="tablenav-pages">';
        if ($prevPage) {
            $prevUrl = add_query_arg(
                [
                    'page' => 'bressol_crm_customers',
                    'q' => $query,
                    'paged' => $prevPage,
                ],
                admin_url('admin.php')
            );
            echo '<a class="button" href="' . esc_url($prevUrl) . '">Prev</a> ';
        }
        if ($nextPage) {
            $nextUrl = add_query_arg(
                [
                    'page' => 'bressol_crm_customers',
                    'q' => $query,
                    'paged' => $nextPage,
                ],
                admin_url('admin.php')
            );
            echo '<a class="button" href="' . esc_url($nextUrl) . '">Next</a>';
        }
        echo '</div></div>';

        echo '</div>';
    }

    private function ensureAccess(): void
    {
        if (!current_user_can(Capabilities::CAP)) {
            wp_die('No autorizado.');
        }
    }

    private function ensure_parent_menu_exists(): void
    {
        // Garantiza que el parent slug 'bressol' exista, para que WP genere URLs tipo admin.php?page=...
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
    private function table_exists(string $table): bool
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }
}
