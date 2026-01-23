<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class EspConsentService
{
    public function set_consent_status(string $email, string $status, string $source, ?int $orderId = null): bool
    {
        if ($email === '' || !in_array($status, ['opt_in', 'opt_out'], true)) {
            return false;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'bressol_esp_consents';
        $tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($tableExists !== $table) {
            return false;
        }

        $now = current_time('mysql');
        $inserted = $wpdb->replace(
            $table,
            [
                'user_id' => null,
                'email' => $email,
                'status' => $status,
                'source' => $source,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        return $inserted !== false;
    }
    
    public function get_consent_status(string $email): ?string
    {
        $details = $this->get_consent_details($email);
        if (!$details) {
            return null;
        }

        return $details['status'] ?? null;
    }

    /** @return array<string, string|null>|null */
    public function get_consent_details(string $email): ?array
    {
        if ($email === '') {
            return null;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'bressol_esp_consents';
        $tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($tableExists !== $table) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT status, updated_at, source FROM {$table} WHERE email = %s LIMIT 1",
                $email
            )
        );

        if (!$row) {
            return null;
        }

        return [
            'status' => (string) $row->status,
            'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
            'source' => $row->source ? (string) $row->source : null,
            'proof' => null,
        ];
    }
}
