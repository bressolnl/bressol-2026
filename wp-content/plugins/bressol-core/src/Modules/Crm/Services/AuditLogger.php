<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class AuditLogger
{
    /** @param array<string, mixed>|null $context */
    public function log(string $action, string $entityType, ?int $entityId, ?int $actorId, ?array $context = null): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_audit_logs';
        $timestamp = current_time('mysql');

        $data = [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'actor_id' => $actorId,
            'context' => $context === null ? null : wp_json_encode($context),
            'created_at' => $timestamp,
        ];

        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($data as $column => $value) {
            $columns[] = $column;

            if ($value === null) {
                $placeholders[] = 'NULL';
                continue;
            }

            if (is_int($value)) {
                $placeholders[] = '%d';
                $values[] = $value;
                continue;
            }

            $placeholders[] = '%s';
            $values[] = $value;
        }

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        if ($values !== []) {
            $sql = $wpdb->prepare($sql, $values);
        }

        $wpdb->query($sql);
    }
}
