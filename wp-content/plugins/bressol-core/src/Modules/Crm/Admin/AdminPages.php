<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Admin;

use Bressol\Modules\Crm\Services\AuditLogger;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Crm\Services\PointsService;
use Bressol\Modules\Crm\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private const PER_PAGE = 20;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            'Bressol CRM',
            'Bressol CRM',
            'manage_woocommerce',
            'bressol-crm',
            [$this, 'render_customers_list'],
            'dashicons-id'
        );

        add_submenu_page(
            'bressol-crm',
            'Customers',
            'Customers',
            'manage_woocommerce',
            'bressol-crm',
            [$this, 'render_customers_list']
        );

        add_submenu_page(
            'bressol-crm',
            'Customer Detail',
            'Customer Detail',
            'manage_woocommerce',
            'bressol-crm-customer',
            [$this, 'render_customer_detail']
        );

        add_submenu_page(
            'bressol-crm',
            'Points Ledger',
            'Points Ledger',
            'manage_woocommerce',
            'bressol-crm-points',
            [$this, 'render_points_ledger']
        );

        add_submenu_page(
            'bressol-crm',
            'Redemptions',
            'Redemptions',
            'manage_woocommerce',
            'bressol-crm-redemptions',
            [$this, 'render_redemptions']
        );

        add_submenu_page(
            'bressol-crm',
            'Settings',
            'Settings',
            'manage_woocommerce',
            'bressol-crm-settings',
            [$this, 'render_settings']
        );
    }

    public function render_customers_list(): void
    {
        global $wpdb;

        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset = ($page - 1) * self::PER_PAGE;

        $email_search = isset($_GET['crm_email']) ? sanitize_text_field(wp_unslash((string) $_GET['crm_email'])) : '';
        $type_filter = isset($_GET['crm_type']) ? sanitize_text_field(wp_unslash((string) $_GET['crm_type'])) : '';
        $type_filter = in_array($type_filter, ['b2c', 'b2b'], true) ? $type_filter : '';

        $where = [];
        $args = [];

        if ($email_search !== '') {
            $where[] = 'email LIKE %s';
            $args[] = '%' . $wpdb->esc_like($email_search) . '%';
        }

        if ($type_filter !== '') {
            $where[] = 'customer_type = %s';
            $args[] = $type_filter;
        }

        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $table = $wpdb->prefix . 'bressol_crm_customers';

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $total = $args ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $args)) : (int) $wpdb->get_var($count_sql);

        $list_sql = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $list_args = array_merge($args, [self::PER_PAGE, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_args), ARRAY_A);

        $total_pages = max(1, (int) ceil($total / self::PER_PAGE));
        $base_url = admin_url('admin.php?page=bressol-crm');

        echo '<div class="wrap">';
        echo '<h1>Bressol CRM - Customers</h1>';

        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="bressol-crm" />';
        echo '<input type="search" name="crm_email" placeholder="Search email" value="' . esc_attr($email_search) . '" /> ';
        echo '<select name="crm_type">';
        echo '<option value="">All types</option>';
        echo '<option value="b2c"' . selected($type_filter, 'b2c', false) . '>B2C</option>';
        echo '<option value="b2b"' . selected($type_filter, 'b2b', false) . '>B2B</option>';
        echo '</select> ';
        echo '<button class="button">Filter</button>';
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Email</th><th>Type</th><th>Business Type</th><th>Total Spent</th><th>Orders</th><th>Last Order</th>';
        echo '</tr></thead><tbody>';

        if ($rows) {
            foreach ($rows as $row) {
                $detail_url = add_query_arg(
                    ['page' => 'bressol-crm-customer', 'customer_id' => (int) $row['id']],
                    admin_url('admin.php')
                );

                echo '<tr>';
                echo '<td>' . esc_html((string) $row['id']) . '</td>';
                echo '<td><a href="' . esc_url($detail_url) . '">' . esc_html($row['email']) . '</a></td>';
                echo '<td>' . esc_html($row['customer_type']) . '</td>';
                echo '<td>' . esc_html((string) $row['business_type']) . '</td>';
                echo '<td>' . esc_html(number_format_i18n((float) $row['total_spent'], 2)) . '</td>';
                echo '<td>' . esc_html((string) $row['order_count']) . '</td>';
                echo '<td>' . esc_html((string) $row['last_order_at']) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">No customers yet.</td></tr>';
        }

        echo '</tbody></table>';

        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $link = add_query_arg(
                    [
                        'paged' => $i,
                        'crm_email' => $email_search,
                        'crm_type' => $type_filter,
                    ],
                    $base_url
                );
                $class = $i === $page ? ' class="current-page"' : '';
                echo '<span' . $class . '><a href="' . esc_url($link) . '">' . esc_html((string) $i) . '</a></span> ';
            }
            echo '</div></div>';
        }

        echo '</div>';
    }

    public function render_customer_detail(): void
    {
        $customer_id = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
        $customer_service = new CustomerService();
        $customer = $customer_id ? $customer_service->get_by_id($customer_id) : null;
        $points_service = new PointsService();

        echo '<div class="wrap">';
        echo '<h1>Bressol CRM - Customer Detail</h1>';

        if (!$customer) {
            echo '<p>Customer not found.</p>';
            echo '</div>';
            return;
        }

        $message = $this->handle_customer_updates($customer_id);
        if ($message) {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        }

        $balance = $points_service->get_balance($customer_id);

        echo '<h2>Profile</h2>';
        echo '<table class="widefat">';
        echo '<tr><th>Email</th><td>' . esc_html($customer['email']) . '</td></tr>';
        echo '<tr><th>Customer Type</th><td>' . esc_html($customer['customer_type']) . '</td></tr>';
        echo '<tr><th>Business Type</th><td>' . esc_html((string) $customer['business_type']) . '</td></tr>';
        echo '<tr><th>Total Spent</th><td>' . esc_html(number_format_i18n((float) $customer['total_spent'], 2)) . '</td></tr>';
        echo '<tr><th>Order Count</th><td>' . esc_html((string) $customer['order_count']) . '</td></tr>';
        echo '<tr><th>Last Order</th><td>' . esc_html((string) $customer['last_order_at']) . '</td></tr>';
        echo '<tr><th>Points Balance</th><td>' . esc_html((string) $balance) . '</td></tr>';
        echo '</table>';

        echo '<h2>Edit Customer</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_crm_customer_update', 'bressol_crm_nonce');
        echo '<table class="form-table">';
        echo '<tr><th scope="row">Customer Type</th><td>';
        echo '<select name="customer_type">';
        echo '<option value="b2c"' . selected($customer['customer_type'], 'b2c', false) . '>B2C</option>';
        echo '<option value="b2b"' . selected($customer['customer_type'], 'b2b', false) . '>B2B</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '<tr><th scope="row">Business Type</th><td>';
        echo '<input type="text" name="business_type" value="' . esc_attr((string) $customer['business_type']) . '" class="regular-text" />';
        echo '</td></tr>';
        echo '</table>';
        echo '<p><input type="submit" class="button button-primary" name="bressol_crm_update_customer" value="Save" /></p>';
        echo '</form>';

        echo '<h2>Redeem Points</h2>';
        echo '<form method="post">';
        wp_nonce_field('bressol_crm_redeem_points', 'bressol_crm_redeem_nonce');
        echo '<table class="form-table">';
        echo '<tr><th scope="row">Redemption Type</th><td>';
        echo '<select name="redemption_type">';
        echo '<option value="gift">Gift</option>';
        echo '<option value="discount">Discount</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '<tr><th scope="row">Points</th><td>';
        echo '<input type="number" min="1" name="points" value="" />';
        echo '</td></tr>';
        echo '<tr><th scope="row">Reference</th><td>';
        echo '<input type="text" name="reference" value="" class="regular-text" />';
        echo '</td></tr>';
        echo '<tr><th scope="row">Notes</th><td>';
        echo '<textarea name="notes" rows="3" class="large-text"></textarea>';
        echo '</td></tr>';
        echo '</table>';
        echo '<p><input type="submit" class="button" name="bressol_crm_redeem_points" value="Redeem" /></p>';
        echo '</form>';

        $ledger_url = add_query_arg(
            ['page' => 'bressol-crm-points', 'customer_id' => $customer_id],
            admin_url('admin.php')
        );
        echo '<p><a class="button" href="' . esc_url($ledger_url) . '">View Points Ledger</a></p>';

        echo '</div>';
    }

    public function render_points_ledger(): void
    {
        global $wpdb;

        $customer_id = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
        $table = $wpdb->prefix . 'bressol_crm_points_ledger';

        $where_sql = '';
        $args = [];
        if ($customer_id > 0) {
            $where_sql = 'WHERE customer_id = %d';
            $args[] = $customer_id;
        }

        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY earned_at DESC";
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        echo '<div class="wrap">';
        echo '<h1>Bressol CRM - Points Ledger</h1>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Customer</th><th>Source</th><th>Points</th><th>Status</th><th>Earned</th><th>Expires</th><th>Notes</th>';
        echo '</tr></thead><tbody>';

        if ($rows) {
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td>' . esc_html((string) $row['id']) . '</td>';
                echo '<td>' . esc_html((string) $row['customer_id']) . '</td>';
                echo '<td>' . esc_html($row['source_type']) . ' #' . esc_html((string) $row['source_id']) . '</td>';
                echo '<td>' . esc_html((string) $row['points']) . '</td>';
                echo '<td>' . esc_html($row['status']) . '</td>';
                echo '<td>' . esc_html($row['earned_at']) . '</td>';
                echo '<td>' . esc_html($row['expires_at']) . '</td>';
                echo '<td>' . esc_html((string) $row['notes']) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8">No points activity yet.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_redemptions(): void
    {
        global $wpdb;

        $customer_id = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
        $table = $wpdb->prefix . 'bressol_crm_points_redemptions';

        $where_sql = '';
        $args = [];
        if ($customer_id > 0) {
            $where_sql = 'WHERE customer_id = %d';
            $args[] = $customer_id;
        }

        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC";
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        echo '<div class="wrap">';
        echo '<h1>Bressol CRM - Redemptions</h1>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Customer</th><th>Type</th><th>Points</th><th>Reference</th><th>Notes</th><th>Date</th>';
        echo '</tr></thead><tbody>';

        if ($rows) {
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td>' . esc_html((string) $row['id']) . '</td>';
                echo '<td>' . esc_html((string) $row['customer_id']) . '</td>';
                echo '<td>' . esc_html($row['redemption_type']) . '</td>';
                echo '<td>' . esc_html((string) $row['points_used']) . '</td>';
                echo '<td>' . esc_html((string) $row['reference']) . '</td>';
                echo '<td>' . esc_html((string) $row['notes']) . '</td>';
                echo '<td>' . esc_html($row['created_at']) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">No redemptions yet.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_settings(): void
    {
        $settings = new Settings();

        if (isset($_POST['bressol_crm_save_settings']) && check_admin_referer('bressol_crm_settings', 'bressol_crm_settings_nonce')) {
            $settings->update([
                'b2c_points_per_euro' => isset($_POST['b2c_points_per_euro']) ? (float) $_POST['b2c_points_per_euro'] : null,
                'b2b_points_per_euro' => isset($_POST['b2b_points_per_euro']) ? (float) $_POST['b2b_points_per_euro'] : null,
                'expiry_months' => isset($_POST['expiry_months']) ? (int) $_POST['expiry_months'] : null,
            ]);

            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $current = $settings->get_all();

        echo '<div class="wrap">';
        echo '<h1>Bressol CRM - Settings</h1>';
        echo '<form method="post">';
        wp_nonce_field('bressol_crm_settings', 'bressol_crm_settings_nonce');
        echo '<table class="form-table">';
        echo '<tr><th scope="row">B2C points per €</th><td><input type="number" step="0.1" name="b2c_points_per_euro" value="' . esc_attr((string) $current['b2c_points_per_euro']) . '" /></td></tr>';
        echo '<tr><th scope="row">B2B points per €</th><td><input type="number" step="0.1" name="b2b_points_per_euro" value="' . esc_attr((string) $current['b2b_points_per_euro']) . '" /></td></tr>';
        echo '<tr><th scope="row">Expiry months</th><td><input type="number" min="1" name="expiry_months" value="' . esc_attr((string) $current['expiry_months']) . '" /></td></tr>';
        echo '</table>';
        echo '<p class="description">"Ciclo Nido": los puntos expiran X meses después de ser ganados, por movimiento individual.</p>';
        echo '<p><input type="submit" class="button button-primary" name="bressol_crm_save_settings" value="Save Settings" /></p>';
        echo '</form>';
        echo '</div>';
    }

    private function handle_customer_updates(int $customer_id): ?string
    {
        if (isset($_POST['bressol_crm_update_customer']) && check_admin_referer('bressol_crm_customer_update', 'bressol_crm_nonce')) {
            global $wpdb;

            $customer_type = isset($_POST['customer_type']) && $_POST['customer_type'] === 'b2b' ? 'b2b' : 'b2c';
            $business_type = isset($_POST['business_type']) ? sanitize_text_field((string) $_POST['business_type']) : '';
            $business_type = $business_type !== '' ? $business_type : null;

            $table = $wpdb->prefix . 'bressol_crm_customers';
            $wpdb->update(
                $table,
                [
                    'customer_type' => $customer_type,
                    'business_type' => $business_type,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $customer_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            (new AuditLogger())->log(
                'customer_updated',
                'customer',
                $customer_id,
                get_current_user_id() ?: null,
                [
                    'customer_type' => $customer_type,
                    'business_type' => $business_type,
                ]
            );

            return 'Customer updated.';
        }

        if (isset($_POST['bressol_crm_redeem_points']) && check_admin_referer('bressol_crm_redeem_points', 'bressol_crm_redeem_nonce')) {
            $points = isset($_POST['points']) ? (int) $_POST['points'] : 0;
            $redemption_type = isset($_POST['redemption_type']) ? sanitize_text_field((string) $_POST['redemption_type']) : 'gift';
            $reference = isset($_POST['reference']) ? sanitize_text_field((string) $_POST['reference']) : '';
            $notes = isset($_POST['notes']) ? sanitize_textarea_field((string) $_POST['notes']) : '';

            $result = (new PointsService())->redeem_points($customer_id, $points, $redemption_type, $reference, $notes);

            return $result ? 'Points redeemed.' : 'Unable to redeem points.';
        }

        return null;
    }
}
