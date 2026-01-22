<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

use DateTimeImmutable;
use DateTimeZone;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

final class PointsService
{
    private Settings $settings;
    private AuditLogger $audit_logger;

    public function __construct()
    {
        $this->settings = new Settings();
        $this->audit_logger = new AuditLogger();
    }

    public function award_points_for_order(int $customer_id, array $customer, WC_Order $order): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_ledger';
        $order_id = (int) $order->get_id();

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE customer_id = %d AND source_type = %s AND source_id = %d",
                $customer_id,
                'order',
                $order_id
            )
        );

        if ($existing) {
            return;
        }

        $order_total = (float) $order->get_total();
        $points = $this->calculate_points($order_total, (string) $customer['customer_type']);
        if ($points <= 0) {
            return;
        }

        $paid_date = $order->get_date_paid();
        $created_date = $order->get_date_created();
        $earned_at = $paid_date ? $paid_date->date('Y-m-d H:i:s') : ($created_date ? $created_date->date('Y-m-d H:i:s') : current_time('mysql'));
        $expires_at = $this->calculate_expiry($earned_at);

        $wpdb->insert(
            $table,
            [
                'customer_id' => $customer_id,
                'source_type' => 'order',
                'source_id' => $order_id,
                'points' => $points,
                'status' => 'active',
                'earned_at' => $earned_at,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        $this->audit_logger->log(
            'points_earned',
            'points_ledger',
            (int) $wpdb->insert_id,
            get_current_user_id() ?: null,
            [
                'customer_id' => $customer_id,
                'order_id' => $order_id,
                'points' => $points,
                'expires_at' => $expires_at,
            ]
        );
    }

    public function refund_points_for_order(int $customer_id, array $customer, int $order_id, int $refund_id): void
    {
        global $wpdb;

        $refund = wc_get_order($refund_id);
        if (!$refund instanceof WC_Order) {
            return;
        }

        $table = $wpdb->prefix . 'bressol_crm_points_ledger';
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE customer_id = %d AND source_type = %s AND source_id = %d",
                $customer_id,
                'refund',
                $refund_id
            )
        );

        if ($existing) {
            return;
        }

        $refund_total = abs((float) $refund->get_total());
        $points = $this->calculate_points($refund_total, (string) $customer['customer_type']);
        if ($points <= 0) {
            return;
        }

        $refund_date = $refund->get_date_created();
        $earned_at = $refund_date ? $refund_date->date('Y-m-d H:i:s') : current_time('mysql');

        $wpdb->insert(
            $table,
            [
                'customer_id' => $customer_id,
                'source_type' => 'refund',
                'source_id' => $refund_id,
                'points' => -1 * $points,
                'status' => 'active',
                'earned_at' => $earned_at,
                'expires_at' => $earned_at,
                'notes' => sprintf('Refund for order #%d', $order_id),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        $this->audit_logger->log(
            'points_refunded',
            'points_ledger',
            (int) $wpdb->insert_id,
            get_current_user_id() ?: null,
            [
                'customer_id' => $customer_id,
                'order_id' => $order_id,
                'refund_id' => $refund_id,
                'points' => $points,
            ]
        );
    }

    public function redeem_points(int $customer_id, int $points, string $redemption_type, string $reference, string $notes = ''): ?int
    {
        global $wpdb;

        $points = max(0, $points);
        if ($points <= 0) {
            return null;
        }

        $balance = $this->get_balance($customer_id);
        if ($balance < $points) {
            return null;
        }

        $redemptions_table = $wpdb->prefix . 'bressol_crm_points_redemptions';
        $ledger_table = $wpdb->prefix . 'bressol_crm_points_ledger';

        $wpdb->insert(
            $redemptions_table,
            [
                'customer_id' => $customer_id,
                'redemption_type' => $redemption_type,
                'points_used' => $points,
                'reference' => $reference,
                'notes' => $notes,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s']
        );

        $redemption_id = (int) $wpdb->insert_id;
        $earned_at = current_time('mysql');

        $wpdb->insert(
            $ledger_table,
            [
                'customer_id' => $customer_id,
                'source_type' => 'redemption',
                'source_id' => $redemption_id,
                'points' => -1 * $points,
                'status' => 'active',
                'earned_at' => $earned_at,
                'expires_at' => $earned_at,
                'notes' => $notes,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        $this->audit_logger->log(
            'points_redeemed',
            'points_redemption',
            $redemption_id,
            get_current_user_id() ?: null,
            [
                'customer_id' => $customer_id,
                'points' => $points,
                'redemption_type' => $redemption_type,
            ]
        );

        return $redemption_id;
    }

    public function get_balance(int $customer_id): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_ledger';
        $now = current_time('mysql');

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE customer_id = %d AND status = %s AND (points < 0 OR expires_at > %s)",
                $customer_id,
                'active',
                $now
            )
        );

        return (int) $total;
    }

    public function expire_points(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_ledger';
        $now = current_time('mysql');

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, customer_id, points FROM {$table} WHERE status = %s AND expires_at <= %s AND points > 0",
                'active',
                $now
            )
        );

        if (!$entries) {
            return;
        }

        foreach ($entries as $entry) {
            $wpdb->update(
                $table,
                [
                    'status' => 'expired',
                    'expired_at' => $now,
                ],
                ['id' => (int) $entry->id],
                ['%s', '%s'],
                ['%d']
            );

            $this->audit_logger->log(
                'points_expired',
                'points_ledger',
                (int) $entry->id,
                null,
                [
                    'customer_id' => (int) $entry->customer_id,
                    'points' => (int) $entry->points,
                ]
            );
        }
    }

    private function calculate_points(float $order_total, string $customer_type): int
    {
        $rate = $customer_type === 'b2b'
            ? $this->settings->get_float('b2b_points_per_euro')
            : $this->settings->get_float('b2c_points_per_euro');

        return (int) floor($order_total * $rate);
    }

    private function calculate_expiry(string $earned_at): string
    {
        // "Ciclo Nido": los puntos expiran por movimiento, nunca en bloque.
        $months = $this->settings->get_int('expiry_months');
        $timezone = wp_timezone();

        $earned = new DateTimeImmutable($earned_at, $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone('UTC'));
        $expires = $earned->modify(sprintf('+%d months', $months));

        return $expires->format('Y-m-d H:i:s');
    }
}
