<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class PointsService
{
    private Settings $settings;
    private AuditLogger $auditLogger;
    private ?bool $loyaltyColumnExists = null;

    public function __construct(Settings $settings, AuditLogger $auditLogger)
    {
        $this->settings = $settings;
        $this->auditLogger = $auditLogger;
    }

    public function expire_points(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_ledger';
        $now = current_time('mysql');

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, expired_at = %s WHERE status = %s AND expires_at < %s",
                'expired',
                $now,
                'active',
                $now
            )
        );

        return (int) $updated;
    }

    public function get_balance(int $customerId): int
    {
        global $wpdb;

        $ledgerTable = $wpdb->prefix . 'bressol_crm_points_ledger';
        $redemptionsTable = $wpdb->prefix . 'bressol_crm_points_redemptions';
        $now = current_time('mysql');

        $ledger = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM {$ledgerTable} WHERE customer_id = %d AND status = %s AND expires_at >= %s",
                $customerId,
                'active',
                $now
            )
        );

        $redemptions = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(points_used), 0) FROM {$redemptionsTable} WHERE customer_id = %d",
                $customerId
            )
        );

        return max(0, $ledger - $redemptions);
    }

    public function award_points_for_order(int $customerId, object $order, string $customerType, string $source = 'order'): bool
    {
        if (!method_exists($order, 'get_total') || !method_exists($order, 'get_id')) {
            return false;
        }

        $orderId = (int) $order->get_id();
        $total = (float) $order->get_total();

        if ($orderId <= 0 || $total <= 0) {
            return false;
        }

        $state = $this->get_customer_state($customerId);
        if (!$state) {
            $this->log_points_skip($customerId, 'points_skipped_customer_inactive', $orderId, $source);
            return false;
        }

        if ($state['status'] !== 'active') {
            $this->log_points_skip($customerId, 'points_skipped_customer_inactive', $orderId, $source);
            return false;
        }

        if (!$this->loyalty_column_exists() || $state['loyalty_enabled'] !== 1) {
            $this->log_points_skip($customerId, 'points_skipped_no_loyalty', $orderId, $source);
            return false;
        }

        if ($this->ledger_entry_exists($customerId, 'order', $orderId)) {
            $this->log_points_skip($customerId, 'points_skipped_duplicate_order', $orderId, $source);
            return false;
        }

        $rate = $this->settings->get_points_rate($customerType);
        $points = (int) round($total * $rate);
        if ($points <= 0) {
            return false;
        }

        $earnedAt = current_time('mysql');
        if (method_exists($order, 'get_date_created')) {
            $created = $order->get_date_created();
            if ($created) {
                $earnedAt = $created->date('Y-m-d H:i:s');
            }
        }

        $expiresAt = (new \DateTimeImmutable($earnedAt, wp_timezone()))
            ->modify('+' . $this->settings->get_expiry_months() . ' months')
            ->format('Y-m-d H:i:s');

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_points_ledger';

        $inserted = $wpdb->insert(
            $table,
            [
                'customer_id' => $customerId,
                'source_type' => 'order',
                'source_id' => $orderId,
                'points' => $points,
                'status' => 'active',
                'earned_at' => $earnedAt,
                'expires_at' => $expiresAt,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        return (bool) $inserted;
    }

    public function register_refund_points(int $customerId, object $refund, string $customerType): bool
    {
        if (!method_exists($refund, 'get_total') || !method_exists($refund, 'get_id')) {
            return false;
        }

        $refundId = (int) $refund->get_id();
        $total = (float) $refund->get_total();

        if ($refundId <= 0 || $total == 0.0) {
            return false;
        }

        if ($this->ledger_entry_exists($customerId, 'refund', $refundId)) {
            return false;
        }

        $rate = $this->settings->get_points_rate($customerType);
        $points = (int) round($total * $rate);
        if ($points === 0) {
            return false;
        }

        if ($points > 0) {
            $points = -$points;
        }

        $earnedAt = current_time('mysql');

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_points_ledger';

        $inserted = $wpdb->insert(
            $table,
            [
                'customer_id' => $customerId,
                'source_type' => 'refund',
                'source_id' => $refundId,
                'points' => $points,
                'status' => 'active',
                'earned_at' => $earnedAt,
                'expires_at' => $earnedAt,
                'created_at' => $earnedAt,
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        return (bool) $inserted;
    }

    public function create_redemption(
        int $customerId,
        int $pointsUsed,
        string $redemptionType,
        ?string $reference,
        ?string $notes
    ): int {
        if ($pointsUsed <= 0) {
            return 0;
        }

        if (!$this->is_customer_active_and_loyal($customerId)) {
            return 0;
        }

        if ($this->get_balance($customerId) < $pointsUsed) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_points_redemptions';

        $inserted = $wpdb->insert(
            $table,
            [
                'customer_id' => $customerId,
                'redemption_type' => $redemptionType,
                'points_used' => $pointsUsed,
                'reference' => $reference,
                'notes' => $notes,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function count_ledger(?int $customerId = null): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_ledger';
        if ($customerId === null) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE customer_id = %d", $customerId)
        );
    }

    public function list_ledger(?int $customerId, int $limit, int $offset): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_ledger';
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        if ($customerId === null) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} ORDER BY earned_at DESC LIMIT %d OFFSET %d",
                    $limit,
                    $offset
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY earned_at DESC LIMIT %d OFFSET %d",
                $customerId,
                $limit,
                $offset
            )
        );
    }

    public function count_redemptions(?int $customerId = null): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_redemptions';
        if ($customerId === null) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE customer_id = %d", $customerId)
        );
    }

    public function list_redemptions(?int $customerId, int $limit, int $offset): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_redemptions';
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        if ($customerId === null) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $limit,
                    $offset
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $customerId,
                $limit,
                $offset
            )
        );
    }

    private function ledger_entry_exists(int $customerId, string $sourceType, int $sourceId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_points_ledger';
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE customer_id = %d AND source_type = %s AND source_id = %d LIMIT 1",
                $customerId,
                $sourceType,
                $sourceId
            )
        );

        return $existing !== null;
    }

    private function is_customer_active_and_loyal(int $customerId): bool
    {
        $state = $this->get_customer_state($customerId);
        if (!$state) {
            return false;
        }

        if ($state['status'] !== 'active') {
            return false;
        }

        if (!$this->loyalty_column_exists()) {
            return false;
        }

        return $state['loyalty_enabled'] === 1;
    }

    /** @return array{status: string, loyalty_enabled: int}|null */
    private function get_customer_state(int $customerId): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';

        if ($this->loyalty_column_exists()) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT status, loyalty_enabled FROM {$table} WHERE id = %d",
                    $customerId
                ),
                ARRAY_A
            );
        } else {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT status FROM {$table} WHERE id = %d",
                    $customerId
                ),
                ARRAY_A
            );
        }

        if (!$row) {
            return null;
        }

        return [
            'status' => (string) ($row['status'] ?? 'inactive'),
            'loyalty_enabled' => (int) ($row['loyalty_enabled'] ?? 0),
        ];
    }

    private function log_points_skip(int $customerId, string $reasonCode, int $orderId, string $source): void
    {
        $context = [
            'reason_code' => $reasonCode,
            'source' => $source,
        ];

        if ($orderId > 0) {
            $context['order_id'] = $orderId;
        }

        $this->auditLogger->log('points_skipped', 'customer', $customerId, null, $context);
    }

    private function loyalty_column_exists(): bool
    {
        if ($this->loyaltyColumnExists !== null) {
            return $this->loyaltyColumnExists;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $column = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'loyalty_enabled')
        );

        $this->loyaltyColumnExists = $column !== null;

        return $this->loyaltyColumnExists;
    }
}
