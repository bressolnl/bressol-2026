<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class AuditLogger
{
    /**
     * Log mínimo. No debe romper nunca el flujo principal.
     *
     * @param array<string, mixed> $context
     */
    public function log(string $action, string $entityType, ?int $entityId, ?int $actorId = null, array $context = []): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_audit_logs';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return;
        }

        $payload = $context !== [] ? wp_json_encode($context) : null;
        $entityId = ($entityId !== null && $entityId > 0) ? $entityId : null;
        $actorId = ($actorId !== null && $actorId > 0) ? $actorId : null;

        $wpdb->insert(
            $table,
            [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'actor_id' => $actorId,
                'context' => $payload,
                'created_at' => current_time('mysql'),
            ]
        );
    }
}
