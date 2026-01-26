<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Crm\Services\AuditLogger as CrmAuditLogger;

if (!defined('ABSPATH')) {
    exit;
}

final class AuditLogger
{
    /** @param array<string, mixed> $context */
    public function log(string $action, array $context, ?int $entityId = null, string $entityType = 'purchasing'): void
    {
        if (!class_exists(CrmAuditLogger::class)) {
            return;
        }

        $action = $this->normalize_action($action);
        $logger = new CrmAuditLogger();
        $logger->log($action, $entityType, $entityId, get_current_user_id(), $this->sanitize_context($context));
    }

    private function normalize_action(string $action): string
    {
        $action = trim($action);
        if ($action === '') {
            return 'purchasing_event';
        }

        if (strpos($action, 'purchasing_') === 0) {
            return $action;
        }

        return 'purchasing_' . $action;
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    private function sanitize_context(array $context): array
    {
        $allowed = [
            'supplier_id',
            'po_id',
            'receiving_id',
            'po_line_id',
            'status',
            'from',
            'to',
            'currency',
            'tax_rate_bp',
            'customs_fees_cents',
            'line_count',
            'qty',
            'qty_total',
            'total_qty',
            'total_lines',
            'totals_cents',
            'result',
            'reason',
            'window_weeks',
            'reminder_weeks_before',
        ];

        $payload = [
            'module' => 'purchasing',
        ];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }
            $value = $context[$key];
            if (in_array($key, ['supplier_id', 'po_id', 'receiving_id', 'po_line_id', 'tax_rate_bp', 'customs_fees_cents', 'line_count', 'qty', 'qty_total', 'total_qty', 'total_lines', 'totals_cents', 'window_weeks', 'reminder_weeks_before'], true)) {
                $payload[$key] = $value === null ? null : (int) $value;
                continue;
            }
            $payload[$key] = $value === null ? null : (string) $value;
        }

        return $payload;
    }
}
