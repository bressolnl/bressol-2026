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
     */
    public function create_redemption(
        int $customerId,
        int $pointsUsed,
        string $redemptionType,
        string $reference,
        ?string $notes
    ): int {
        if ($customerId <= 0 || $pointsUsed <= 0) {
            return 0;
        }

        global $wpdb;
        $redemptions = $wpdb->prefix . 'bressol_crm_points_redemptions';
        $ledger = $wpdb->prefix . 'bressol_crm_points_ledger';

        $now = current_time('mysql');

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
}
