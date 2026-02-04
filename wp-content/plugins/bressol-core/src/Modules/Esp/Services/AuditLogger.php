<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class AuditLogger
{
    public function log(string $action, string $entityType, ?int $entityId, array $context = []): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_esp_audit_logs';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'context' => wp_json_encode($context),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );
    }
}
