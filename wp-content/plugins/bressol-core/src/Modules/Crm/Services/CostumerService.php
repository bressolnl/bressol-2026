<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerService
{
    private AuditLogger $audit_logger;

    public function __construct()
    {
        $this->audit_logger = new AuditLogger();
    }

    public function get_by_id(int $customer_id): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $customer_id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function get_or_create_from_order(WC_Order $order): int
    {
        global $wpdb;

        $email = (string) $order->get_billing_email();
        $user_id = (int) $order->get_user_id();
        $customer_type = $this->resolve_customer_type($order, $email, $user_id);
        $business_type = $this->resolve_business_type($order);

        $table = $wpdb->prefix . 'bressol_crm_customers';

        $existing_id = $this->find_existing_customer_id($email, $customer_type, $user_id);
        if ($existing_id) {
            $wpdb->update(
                $table,
                [
                    'wc_customer_id' => $user_id ?: null,
                    'user_id' => $user_id ?: null,
                    'email' => $email,
                    'customer_type' => $customer_type,
                    'business_type' => $business_type,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $existing_id],
                ['%d', '%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            return (int) $existing_id;
        }

        $wpdb->insert(
            $table,
            [
                'wc_customer_id' => $user_id ?: null,
                'user_id' => $user_id ?: null,
                'email' => $email,
                'customer_type' => $customer_type,
                'business_type' => $business_type,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        $customer_id = (int) $wpdb->insert_id;

        $this->audit_logger->log(
            'customer_created',
            'customer',
            $customer_id,
            get_current_user_id() ?: null,
            [
                'email' => $email,
                'customer_type' => $customer_type,
                'business_type' => $business_type,
            ]
        );

        return $customer_id;
    }

    public function update_cached_metrics(int $customer_id, WC_Order $order): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $user_id = (int) $order->get_user_id();
        $email = (string) $order->get_billing_email();

        $stats = $this->calculate_woocommerce_stats($user_id, $email);

        $wpdb->update(
            $table,
            [
                'total_spent' => $stats['total_spent'],
                'order_count' => $stats['order_count'],
                'last_order_at' => $stats['last_order_at'],
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $customer_id],
            ['%f', '%d', '%s', '%s'],
            ['%d']
        );
    }

    /** @return array{total_spent:float, order_count:int, last_order_at:?string} */
    private function calculate_woocommerce_stats(int $user_id, string $email): array
    {
        if ($user_id > 0 && function_exists('wc_get_customer_total_spent')) {
            $total_spent = (float) wc_get_customer_total_spent($user_id);
            $order_count = (int) wc_get_customer_order_count($user_id);
            $last_order = $this->get_last_order_date_by_user($user_id);

            return [
                'total_spent' => $total_spent,
                'order_count' => $order_count,
                'last_order_at' => $last_order,
            ];
        }

        $orders = wc_get_orders([
            'billing_email' => $email,
            'limit' => -1,
            'status' => ['completed', 'processing'],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $total_spent = 0.0;
        $order_count = 0;
        $last_order_at = null;

        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            $total_spent += (float) $order->get_total();
            $order_count++;

            if (!$last_order_at) {
                $date = $order->get_date_created();
                $last_order_at = $date ? $date->date('Y-m-d H:i:s') : null;
            }
        }

        return [
            'total_spent' => $total_spent,
            'order_count' => $order_count,
            'last_order_at' => $last_order_at,
        ];
    }

    private function get_last_order_date_by_user(int $user_id): ?string
    {
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit' => 1,
            'status' => ['completed', 'processing'],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (!$orders) {
            return null;
        }

        $order = $orders[0];
        if (!$order instanceof WC_Order) {
            return null;
        }

        $date = $order->get_date_created();

        return $date ? $date->date('Y-m-d H:i:s') : null;
    }

    private function resolve_customer_type(WC_Order $order, string $email, int $user_id): string
    {
        $type = apply_filters('bressol_crm_customer_type_from_order', 'b2c', $order, $email, $user_id);

        return $type === 'b2b' ? 'b2b' : 'b2c';
    }

    private function resolve_business_type(WC_Order $order): ?string
    {
        $business_type = apply_filters('bressol_crm_business_type_from_order', '', $order);
        $business_type = is_string($business_type) ? trim($business_type) : '';

        return $business_type !== '' ? $business_type : null;
    }

    private function find_existing_customer_id(string $email, string $customer_type, int $user_id): ?int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';

        if ($user_id > 0) {
            $id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE user_id = %d AND customer_type = %s",
                    $user_id,
                    $customer_type
                )
            );
            if ($id) {
                return (int) $id;
            }
        }

        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE email = %s AND customer_type = %s",
                $email,
                $customer_type
            )
        );

        return $id ? (int) $id : null;
    }
}
