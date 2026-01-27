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
    public function log(string $action, string $entityType, int $entityId, ?int $actorId = null, array $context = []): void
    {
        // MVP: no-op silencioso.
        // Si luego quieres, lo conectamos a la tabla bressol_crm_audit_logs.
    }
}
