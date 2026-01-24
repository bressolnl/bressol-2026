<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

use Bressol\Modules\Crm\Services\AuditLogger;
use Bressol\Modules\Crm\Services\CustomerService;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerLookupService
{
    private CustomerService $customerService;
    private ?AuditLogger $auditLogger;

    public function __construct(?CustomerService $customerService = null, ?AuditLogger $auditLogger = null)
    {
        $this->customerService = $customerService ?? new CustomerService();
        $this->auditLogger = $auditLogger;
    }

    /** @return array<string, mixed>|null */
    public function find_by_public_id(string $publicId): ?array
    {
        $publicId = $this->sanitize_token($publicId);
        if ($publicId === '') {
            return null;
        }

        $customerId = $this->find_customer_id_by_public_id($publicId);
        if ($customerId <= 0) {
            return null;
        }

        return $this->build_payload($customerId, $publicId);
    }

    /** @return array<string, mixed>|null */
    public function find_by_customer_id(int $customerId): ?array
    {
        if ($customerId <= 0) {
            return null;
        }

        $publicId = $this->ensure_public_id($customerId, false);
        if ($publicId === null) {
            return null;
        }

        return $this->build_payload($customerId, $publicId);
    }

    public function ensure_public_id(int $customerId, bool $regenerate = false): ?string
    {
        if ($customerId <= 0) {
            return null;
        }

        $existing = $this->get_customer_meta_value($customerId, 'pos_public_id');
        if ($existing !== null && !$regenerate) {
            return $existing;
        }

        $token = $this->generate_unique_token();
        if ($token === null) {
            return null;
        }

        if (!$this->set_customer_meta_value($customerId, 'pos_public_id', $token)) {
            return null;
        }

        $action = $existing === null ? 'customer_pos_id_generated' : 'customer_pos_id_regenerated';
        $this->log_customer_action($action, $customerId);

        return $token;
    }

    public function regenerate_public_id(int $customerId): ?string
    {
        return $this->ensure_public_id($customerId, true);
    }

    public function mask_public_id(string $publicId): string
    {
        $publicId = $this->sanitize_token($publicId);
        $length = strlen($publicId);
        if ($length <= 8) {
            return $publicId;
        }

        return substr($publicId, 0, 4) . '…' . substr($publicId, -4);
    }

    /** @return array<string, mixed>|null */
    private function build_payload(int $customerId, string $publicId): ?array
    {
        $customer = $this->customerService->get_customer($customerId);
        if (!$customer) {
            return null;
        }

        $displayName = trim((string) ($customer->first_name ?? '') . ' ' . (string) ($customer->last_name ?? ''));
        if ($displayName === '') {
            $displayName = 'Cliente #' . $customerId;
        }

        $marketingEffective = false;
        $email = (string) ($customer->email ?? '');
        if ($email !== '') {
            $marketingState = $this->customerService->get_effective_marketing_state($email);
            $marketingEffective = (bool) ($marketingState['effective_flags']['can_receive_marketing'] ?? false);
        }

        return [
            'customer_id' => $customerId,
            'display_name' => $displayName,
            'masked_public_id' => $this->mask_public_id($publicId),
            'loyalty_enabled' => $this->customerService->is_loyalty_enabled($customerId),
            'marketing_effective' => $marketingEffective,
            'status' => (string) ($customer->status ?? 'unknown'),
        ];
    }

    private function sanitize_token(string $token): string
    {
        $token = trim(sanitize_text_field($token));
        if ($token === '') {
            return '';
        }

        return preg_replace('/[^a-zA-Z0-9_-]/', '', $token) ?? '';
    }

    private function find_customer_id_by_public_id(string $publicId): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customer_meta';
        if (!$this->table_exists($table)) {
            return 0;
        }

        $sql = $wpdb->prepare(
            "SELECT customer_id FROM {$table} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            'pos_public_id',
            $publicId
        );

        return (int) $wpdb->get_var($sql);
    }

    private function get_customer_meta_value(int $customerId, string $metaKey): ?string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customer_meta';
        if (!$this->table_exists($table)) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT meta_value FROM {$table} WHERE customer_id = %d AND meta_key = %s ORDER BY id DESC LIMIT 1",
            $customerId,
            $metaKey
        );

        $value = $wpdb->get_var($sql);
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function set_customer_meta_value(int $customerId, string $metaKey, string $metaValue): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customer_meta';
        if (!$this->table_exists($table)) {
            return false;
        }

        $wpdb->delete($table, [
            'customer_id' => $customerId,
            'meta_key' => $metaKey,
        ], ['%d', '%s']);

        $inserted = $wpdb->insert(
            $table,
            [
                'customer_id' => $customerId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
            ],
            ['%d', '%s', '%s']
        );

        return $inserted !== false;
    }

    private function generate_unique_token(): ?string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = $this->generate_token();
            if ($token === '') {
                continue;
            }
            if ($this->find_customer_id_by_public_id($token) === 0) {
                return $token;
            }
        }

        return null;
    }

    private function generate_token(): string
    {
        $seed = function_exists('wp_generate_password')
            ? wp_generate_password(24, false, false)
            : bin2hex(random_bytes(12));

        $hash = hash_hmac('sha256', $seed, wp_salt('pos_public_id'));

        return substr($hash, 0, 32);
    }

    private function table_exists(string $table): bool
    {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }

    private function log_customer_action(string $action, int $customerId): void
    {
        if (!$this->auditLogger instanceof AuditLogger) {
            return;
        }

        $this->auditLogger->log($action, 'customer', $customerId, get_current_user_id(), [
            'source' => 'pos',
        ]);
    }
}
