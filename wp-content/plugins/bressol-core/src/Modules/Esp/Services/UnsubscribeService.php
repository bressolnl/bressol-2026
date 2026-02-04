<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class UnsubscribeService
{
    public function build_token(string $email, int $listId): string
    {
        $payload = [
            'email' => $email,
            'list_id' => $listId,
            'ts' => time(),
        ];
        $json = wp_json_encode($payload);
        $sig = hash_hmac('sha256', $json, (string) AUTH_SALT);

        $encoded = base64_encode($json . '::' . $sig);
        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    public function handle_request(): void
    {
        $token = isset($_GET['token']) ? (string) wp_unslash($_GET['token']) : '';
        $token = rawurldecode($token);
        if ($token === '') {
            wp_die('Token inválido.');
        }

        $base64 = strtr($token, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($base64, true);
        if (!is_string($decoded) || $decoded === '') {
            wp_die('Token inválido.');
        }

        $parts = explode('::', $decoded);
        if (count($parts) !== 2) {
            wp_die('Token inválido.');
        }

        [$json, $sig] = $parts;
        $expected = hash_hmac('sha256', $json, (string) AUTH_SALT);
        if (!hash_equals($expected, $sig)) {
            wp_die('Token inválido.');
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            wp_die('Token inválido.');
        }

        if (isset($payload['ts']) && is_numeric($payload['ts'])) {
            $ttlSeconds = 180 * DAY_IN_SECONDS;
            $tokenAge = time() - (int) $payload['ts'];
            if ($tokenAge > $ttlSeconds) {
                wp_die('Token inválido.');
            }
        }

        $email = isset($payload['email']) ? sanitize_email((string) $payload['email']) : '';
        $listId = isset($payload['list_id']) ? (int) $payload['list_id'] : 0;
        if ($email === '' || $listId <= 0) {
            wp_die('Token inválido.');
        }

        global $wpdb;
        $membersTable = $wpdb->prefix . 'bressol_esp_list_members';
        $unsubsTable = $wpdb->prefix . 'bressol_esp_unsubscribes';

        $wpdb->update(
            $membersTable,
            ['status' => 'unsubscribed', 'updated_at' => current_time('mysql')],
            ['email' => $email, 'list_id' => $listId],
            ['%s', '%s'],
            ['%s', '%d']
        );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$unsubsTable} (email, list_id, created_at)
                 VALUES (%s, %d, %s)
                 ON DUPLICATE KEY UPDATE created_at = created_at",
                $email,
                $listId,
                current_time('mysql')
            )
        );

        (new AuditLogger())->log('unsubscribed', 'list', $listId, [
            'email' => $email,
        ]);

        echo '<div class="wrap"><h1>Te has dado de baja</h1><p>Tu email ha sido dado de baja correctamente.</p></div>';
        exit;
    }
}
