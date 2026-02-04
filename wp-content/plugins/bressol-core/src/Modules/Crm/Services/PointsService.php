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

    public function __construct(Settings $settings, AuditLogger $auditLogger)
    {
        $this->settings = $settings;
        $this->auditLogger = $auditLogger;
    }

    public function get_balance(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }

        global $wpdb;
        $ledger = $wpdb->prefix . 'bressol_crm_points_ledger';

        // MVP simple: suma de puntos activos no expirados.
        $now = current_time('mysql');
        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0)
                 FROM {$ledger}
                 WHERE customer_id = %d
                   AND status = 'active'
                   AND expires_at >= %s",
                $customerId,
                $now
            )
        );

        return (int) $sum;
    }

    /**
     * Crea un canje y registra un apunte negativo en el ledger.
     * Devuelve id de la redención (int).
     *
     * @param array<string, mixed>|string|null $context
     */
    public function create_redemption(
        int $customerId,
        int $pointsUsed,
        string $redemptionType,
        string $reference,
        $context = null
    ): int {
        if ($customerId <= 0 || $pointsUsed <= 0) {
            return 0;
        }

        global $wpdb;
        $redemptions = $wpdb->prefix . 'bressol_crm_points_redemptions';
        $ledger = $wpdb->prefix . 'bressol_crm_points_ledger';

        $now = current_time('mysql');
        $notes = null;
        if (is_string($context)) {
            $notes = sanitize_text_field($context);
        } elseif (is_array($context)) {
            if (isset($context['notes']) && is_string($context['notes'])) {
                $notes = sanitize_text_field($context['notes']);
            } else {
                $notes = wp_json_encode($context);
            }
        }

        // 1) insert redemption
        $ok = $wpdb->insert(
            $redemptions,
            [
                'customer_id' => $customerId,
                'redemption_type' => $redemptionType,
                'points_used' => $pointsUsed,
                'reference' => $reference !== '' ? $reference : null,
                'notes' => $notes,
                'created_at' => $now,
            ],
            ['%d','%s','%d','%s','%s','%s']
        );

        if (!$ok) {
            return 0;
        }

        $redemptionId = (int) $wpdb->insert_id;

        // 2) ledger negative entry (para que el balance baje)
        // expires_at: ahora (no debería contarse en balance futuro si filtras por expires_at>=now; pero lo dejamos hoy)
        $wpdb->insert(
            $ledger,
            [
                'customer_id' => $customerId,
                'source_type' => 'redemption',
                'source_id' => $redemptionId,
                'points' => -abs($pointsUsed),
                'status' => 'active',
                'earned_at' => $now,
                'expires_at' => $now,
                'expired_at' => null,
                'notes' => $notes,
                'created_at' => $now,
            ],
            ['%d','%s','%d','%d','%s','%s','%s','%s','%s','%s']
        );

        return $redemptionId;
    }
    
    public function award_points_for_order(
        int $customerId,
        int $orderId,
        int $netSalesExclTaxCents,
        string $channel = 'pos'
    ): int {
        if ($customerId <= 0 || $orderId <= 0 || $netSalesExclTaxCents <= 0) {
            return 0;
        }

        global $wpdb;
        $ledger = $wpdb->prefix . 'bressol_crm_points_ledger';
        $customersTable = $wpdb->prefix . 'bressol_crm_customers';

        $ledgerExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ledger)) === $ledger;
        $customersExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $customersTable)) === $customersTable;
        if (!$ledgerExists || !$customersExists) {
            return 0;
        }

        $existing = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$ledger}
                 WHERE customer_id = %d AND source_type = %s AND source_id = %d
                 LIMIT 1",
                $customerId,
                'pos_order',
                $orderId
            )
        );
        if ($existing > 0) {
            return 0;
        }

        $customerType = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT customer_type FROM {$customersTable} WHERE id = %d LIMIT 1",
                $customerId
            )
        );
        $customerType = $customerType !== '' ? $customerType : 'b2c';

        $settings = $this->settings->get_settings();
        $pointsPerEuro = $customerType === 'b2b'
            ? (float) ($settings['b2b_points_per_euro'] ?? 1.0)
            : (float) ($settings['b2c_points_per_euro'] ?? 1.0);

        $points = (int) floor(($netSalesExclTaxCents / 100) * $pointsPerEuro);
        if ($points <= 0) {
            return 0;
        }

        $expiryMonths = (int) ($settings['expiry_months'] ?? 12);
        $expiryMonths = max(1, $expiryMonths);
        $nowTimestamp = current_time('timestamp');
        $expiresTimestamp = strtotime('+' . $expiryMonths . ' months', $nowTimestamp);
        $now = date('Y-m-d H:i:s', $nowTimestamp);
        $expiresAt = $expiresTimestamp ? date('Y-m-d H:i:s', $expiresTimestamp) : $now;

        $notes = wp_json_encode([
            'order_id' => $orderId,
            'channel' => $channel,
        ]);

        $inserted = $wpdb->insert(
            $ledger,
            [
                'customer_id' => $customerId,
                'source_type' => 'pos_order',
                'source_id' => $orderId,
                'points' => $points,
                'status' => 'active',
                'earned_at' => $now,
                'expires_at' => $expiresAt,
                'expired_at' => null,
                'notes' => $notes,
                'created_at' => $now,
            ],
            ['%d','%s','%d','%d','%s','%s','%s','%s','%s','%s']
        );

        return $inserted ? $points : 0;
    }

    public function award_signup_bonus(int $customerId, int $points = 300, ?int $sourceEventId = null): int
    {
        if ($customerId <= 0 || $points <= 0) {
            return 0;
        }

        global $wpdb;
        $ledger = $wpdb->prefix . 'bressol_crm_points_ledger';
        $customersTable = $wpdb->prefix . 'bressol_crm_customers';

        $ledgerExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ledger)) === $ledger;
        $customersExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $customersTable)) === $customersTable;
        if (!$ledgerExists || !$customersExists) {
            return 0;
        }

        $existing = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$ledger}
                 WHERE customer_id = %d AND source_type = %s AND source_id = %d
                 LIMIT 1",
                $customerId,
                'signup_bonus',
                $customerId
            )
        );
        if ($existing > 0) {
            return 0;
        }

        $settings = $this->settings->get_settings();
        $expiryMonths = (int) ($settings['expiry_months'] ?? 12);
        $expiryMonths = max(1, $expiryMonths);
        $nowTimestamp = current_time('timestamp');
        $expiresTimestamp = strtotime('+' . $expiryMonths . ' months', $nowTimestamp);
        $now = date('Y-m-d H:i:s', $nowTimestamp);
        $expiresAt = $expiresTimestamp ? date('Y-m-d H:i:s', $expiresTimestamp) : $now;

        $context = ['reason' => 'signup_bonus'];
        if ($sourceEventId !== null && $sourceEventId > 0) {
            $context['event_id'] = $sourceEventId;
        }

        $inserted = $wpdb->insert(
            $ledger,
            [
                'customer_id' => $customerId,
                'source_type' => 'signup_bonus',
                'source_id' => $customerId,
                'points' => $points,
                'status' => 'active',
                'earned_at' => $now,
                'expires_at' => $expiresAt,
                'expired_at' => null,
                'notes' => wp_json_encode($context),
                'created_at' => $now,
            ],
            ['%d','%s','%d','%d','%s','%s','%s','%s','%s','%s']
        );

        if ($inserted) {
            $this->auditLogger->log('signup_bonus_awarded', 'customer', $customerId, null, $context);
        }

        return $inserted ? $points : 0;
    }
}
