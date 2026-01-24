<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Services;

use Bressol\Modules\Crm\Services\AuditLogger as CrmAuditLogger;

if (!defined('ABSPATH')) {
    exit;
}

final class AuditLogger
{
    /** @param array<string, mixed> $context */
    public function log(string $action, array $context): void
    {
        if (!class_exists(CrmAuditLogger::class)) {
            return;
        }

        $logger = new CrmAuditLogger();
        $logger->log($action, 'sales_export', null, get_current_user_id(), $this->sanitize_context($context));
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    private function sanitize_context(array $context): array
    {
        $filters = isset($context['filters']) && is_array($context['filters'])
            ? FiltersNormalizer::normalize($context['filters'])
            : FiltersNormalizer::normalize([]);

        $rawResult = isset($context['result']) ? (string) $context['result'] : '';
        $result = $this->normalize_result($rawResult);
        $reason = isset($context['reason']) ? (string) $context['reason'] : null;
        if ($result === 'error' && !in_array(strtolower(trim($rawResult)), ['attempted', 'success', 'blocked', 'error'], true)) {
            $reason = 'invalid_result';
        }

        $payload = [
            'module' => 'sales_analytics',
            'export_type' => isset($context['export_type']) ? (string) $context['export_type'] : '',
            'filters' => $filters,
            'include_pii' => !empty($context['include_pii']),
            'result' => $result,
            'reason' => $reason,
            'rows_count' => isset($context['rows_count']) ? (int) $context['rows_count'] : null,
            'request_uri' => $this->sanitize_request_uri(isset($context['request_uri']) ? (string) $context['request_uri'] : ''),
            'exception_class' => isset($context['exception_class']) ? (string) $context['exception_class'] : null,
            'message_truncated' => $this->truncate_message(isset($context['message_truncated']) ? (string) $context['message_truncated'] : ''),
        ];

        if ($payload['message_truncated'] === '') {
            $payload['message_truncated'] = null;
        }

        return $payload;
    }

    private function normalize_result(string $result): string
    {
        $result = strtolower(trim($result));
        if (in_array($result, ['attempted', 'success', 'blocked', 'error'], true)) {
            return $result;
        }

        return 'error';
    }

    private function sanitize_request_uri(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $path = parse_url($value, PHP_URL_PATH);
        return is_string($path) ? $path : '';
    }

    private function truncate_message(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return '';
        }

        if (strlen($message) <= 120) {
            return $message;
        }

        return substr($message, 0, 120);
    }
}
