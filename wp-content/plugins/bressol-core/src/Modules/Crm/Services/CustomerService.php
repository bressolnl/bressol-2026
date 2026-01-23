<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

use Bressol\Modules\Esp\Services\EspConsentService;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerService
{
    private AuditLogger $auditLogger;
    private ?EspConsentService $espConsentService;
    private ?bool $loyaltyColumnExists = null;

    public function __construct(?AuditLogger $auditLogger = null, ?EspConsentService $espConsentService = null)
    {
        $this->auditLogger = $auditLogger ?? new AuditLogger();
        $this->espConsentService = $espConsentService;
    }

    public function get_customer(int $customerId): ?\stdClass
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';

        $customer = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $customerId)
        );

        return $customer ?: null;
    }

    public function get_customer_type(int $customerId): string
    {
        $customer = $this->get_customer($customerId);
        if (!$customer || empty($customer->customer_type)) {
            return 'b2c';
        }

        return (string) $customer->customer_type;
    }

    /** @param array<string, mixed> $filters */
    public function count_customers(array $filters = []): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $where = 'WHERE 1=1';
        $params = [];

        if (!empty($filters['email'])) {
            $where .= ' AND email LIKE %s';
            $params[] = '%' . $wpdb->esc_like((string) $filters['email']) . '%';
        }

        if (!empty($filters['type']) && in_array($filters['type'], ['b2c', 'b2b'], true)) {
            $where .= ' AND customer_type = %s';
            $params[] = $filters['type'];
        }

        if (empty($filters['include_deleted'])) {
            $where .= " AND status != 'deleted'";
        }

        $sql = "SELECT COUNT(*) FROM {$table} {$where}";
        if ($params !== []) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /** @param array<string, mixed> $filters */
    public function list_customers(array $filters, int $limit, int $offset): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $where = 'WHERE 1=1';
        $params = [];

        if (!empty($filters['email'])) {
            $where .= ' AND email LIKE %s';
            $params[] = '%' . $wpdb->esc_like((string) $filters['email']) . '%';
        }

        if (!empty($filters['type']) && in_array($filters['type'], ['b2c', 'b2b'], true)) {
            $where .= ' AND customer_type = %s';
            $params[] = $filters['type'];
        }

        if (empty($filters['include_deleted'])) {
            $where .= " AND status != 'deleted'";
        }

        $sql = "SELECT * FROM {$table} {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public function upsert_from_order(object $order): ?int
    {
        if (!method_exists($order, 'get_billing_email')) {
            return null;
        }

        $email = sanitize_email((string) $order->get_billing_email());
        if ($email === '') {
            return null;
        }

        $company = sanitize_text_field((string) $order->get_billing_company());
        $customerType = $company !== '' ? 'b2b' : 'b2c';

        $consentStatus = $this->get_esp_consent_status($email);
        $canReceiveMarketing = $consentStatus === 'opt_in' ? 1 : 0;
        $canBeProfiled = 1;

        $existing = $this->get_customer_by_email_type($email, $customerType);
        if ($existing && (string) $existing->status === 'deleted') {
            return (int) $existing->id;
        }

        $data = [
            'wc_customer_id' => $this->normalize_nullable_int($this->get_order_customer_id($order)),
            'user_id' => $this->normalize_nullable_int($this->get_order_user_id($order)),
            'email' => $email,
            'first_name' => sanitize_text_field((string) $order->get_billing_first_name()),
            'last_name' => sanitize_text_field((string) $order->get_billing_last_name()),
            'phone' => sanitize_text_field((string) $order->get_billing_phone()),
            'company' => $company !== '' ? $company : null,
            'billing_address_1' => sanitize_text_field((string) $order->get_billing_address_1()),
            'billing_address_2' => sanitize_text_field((string) $order->get_billing_address_2()),
            'billing_city' => sanitize_text_field((string) $order->get_billing_city()),
            'billing_state' => sanitize_text_field((string) $order->get_billing_state()),
            'billing_postcode' => sanitize_text_field((string) $order->get_billing_postcode()),
            'billing_country' => sanitize_text_field((string) $order->get_billing_country()),
            'customer_type' => $customerType,
            'business_type' => null,
            'status' => 'active',
            'source' => 'order',
            'can_be_profiled' => $canBeProfiled,
            'can_receive_marketing' => $canReceiveMarketing,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing) {
            $this->update_customer_fields((int) $existing->id, $data, false);
            return (int) $existing->id;
        }

        $data['created_at'] = current_time('mysql');

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';

        $wpdb->insert($table, $this->filter_null_values($data));

        $customerId = (int) $wpdb->insert_id;

        $this->auditLogger->log('customer_created', 'customer', $customerId, null, [
            'source' => 'order',
            'email' => $this->mask_email($email),
        ]);

        return $customerId ?: null;
    }

    public function update_metrics_from_order(int $customerId, object $order): void
    {
        if (!method_exists($order, 'get_total') || !method_exists($order, 'get_id')) {
            return;
        }

        $orderTotal = (float) $order->get_total();
        $orderId = (int) $order->get_id();
        if ($orderId <= 0) {
            return;
        }

        if ($this->has_order_been_processed($orderId)) {
            return;
        }

        $created = $order->get_date_created();
        $lastOrderAt = $created ? $created->date('Y-m-d H:i:s') : current_time('mysql');

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';

        $current = $this->get_customer($customerId);
        if (!$current) {
            return;
        }

        $totalSpent = (float) $current->total_spent + $orderTotal;
        $orderCount = (int) $current->order_count + 1;

        $wpdb->update(
            $table,
            [
                'total_spent' => $totalSpent,
                'order_count' => $orderCount,
                'last_order_at' => $lastOrderAt,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $customerId],
            ['%f', '%d', '%s', '%s'],
            ['%d']
        );

        $this->mark_order_processed($orderId, $customerId, $orderTotal, $lastOrderAt);
    }

    /** @param array<string, mixed> $data */
    public function update_customer_fields(int $customerId, array $data, bool $logChange = true): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $current = $this->get_customer($customerId);
        if (!$current) {
            return false;
        }

        $filtered = $this->filter_null_values($data);
        if ($filtered === []) {
            return false;
        }

        $espStatus = $this->get_esp_consent_status((string) ($data['email'] ?? $current->email));
        $shouldForceOff = false;
        if ($espStatus === 'opt_out') {
            $requestedMarketing = array_key_exists('can_receive_marketing', $data)
                ? (int) $data['can_receive_marketing']
                : null;
            if ((int) $current->can_receive_marketing === 1 && $requestedMarketing !== 0) {
                $shouldForceOff = true;
            }
            $filtered['can_receive_marketing'] = 0;
        }

        $filtered['updated_at'] = current_time('mysql');

        $updated = $wpdb->update($table, $filtered, ['id' => $customerId]);
        if ($updated === false) {
            return false;
        }

        if ($logChange) {
            $this->auditLogger->log('customer_updated', 'customer', $customerId, get_current_user_id(), [
                'fields' => array_keys($filtered),
            ]);
        }

        if ($shouldForceOff) {
            $this->auditLogger->log('customer_marketing_forced_off', 'customer', $customerId, $logChange ? get_current_user_id() : null, [
                'email' => $this->mask_email((string) ($data['email'] ?? $current->email)),
                'esp_status' => $espStatus,
            ]);
        }

        return true;
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function update_from_admin(int $customerId, array $payload): array
    {
        $current = $this->get_customer($customerId);
        if (!$current) {
            return [
                'ok' => false,
                'code' => 'customer_not_found',
                'error' => 'Cliente no encontrado.',
            ];
        }

        if ((string) $current->status === 'deleted') {
            return [
                'ok' => false,
                'code' => 'customer_deleted_locked',
                'error' => 'Cliente eliminado. Solo se permite restaurar.',
            ];
        }

        if ((string) $current->status === 'anonymized') {
            $piiFields = [
                'email',
                'first_name',
                'last_name',
                'phone',
                'company',
                'billing_address_1',
                'billing_address_2',
                'billing_city',
                'billing_state',
                'billing_postcode',
                'billing_country',
            ];

            foreach ($piiFields as $field) {
                if (array_key_exists($field, $payload)) {
                    return [
                        'ok' => false,
                        'code' => 'customer_anonymized_locked',
                        'error' => 'Cliente anonimizado. No se pueden editar datos personales.',
                    ];
                }
            }

            if (array_key_exists('loyalty_enabled', $payload)) {
                return [
                    'ok' => false,
                    'code' => 'customer_anonymized_locked',
                    'error' => 'Cliente anonimizado. No se puede modificar el programa de puntos.',
                ];
            }
        }

        $email = isset($payload['email']) ? sanitize_email((string) $payload['email']) : null;
        if ($email === '') {
            $email = null;
        }

        $requestedMarketing = array_key_exists('can_receive_marketing', $payload)
            ? (int) (bool) $payload['can_receive_marketing']
            : null;

        $customerType = isset($payload['customer_type']) && in_array($payload['customer_type'], ['b2c', 'b2b'], true)
            ? $payload['customer_type']
            : null;

        $emailChanged = $email !== null && $email !== (string) $current->email;
        $typeChanged = $customerType !== null && $customerType !== (string) $current->customer_type;

        $loyaltyEnabled = null;
        if (array_key_exists('loyalty_enabled', $payload)) {
            if ((string) $current->status !== 'active') {
                return [
                    'ok' => false,
                    'code' => 'customer_status_locked',
                    'error' => 'Cliente no activo. No se puede modificar el programa de puntos.',
                ];
            }

            if ($this->loyalty_column_exists()) {
                $loyaltyEnabled = (int) (bool) $payload['loyalty_enabled'];
            }
        }

        if ($emailChanged || $typeChanged) {
            $checkEmail = $email ?? (string) $current->email;
            $checkType = $customerType ?? (string) $current->customer_type;
            if ($this->exists_customer_by_email_type_except_id($checkEmail, $checkType, $customerId)) {
                return [
                    'ok' => false,
                    'code' => 'customer_duplicate',
                    'error' => 'Ya existe un cliente con ese email y tipo.',
                ];
            }
        }

        $data = [
            'email' => $email,
            'first_name' => isset($payload['first_name']) ? sanitize_text_field((string) $payload['first_name']) : null,
            'last_name' => isset($payload['last_name']) ? sanitize_text_field((string) $payload['last_name']) : null,
            'phone' => isset($payload['phone']) ? sanitize_text_field((string) $payload['phone']) : null,
            'company' => isset($payload['company']) ? sanitize_text_field((string) $payload['company']) : null,
            'billing_address_1' => isset($payload['billing_address_1']) ? sanitize_text_field((string) $payload['billing_address_1']) : null,
            'billing_address_2' => isset($payload['billing_address_2']) ? sanitize_text_field((string) $payload['billing_address_2']) : null,
            'billing_city' => isset($payload['billing_city']) ? sanitize_text_field((string) $payload['billing_city']) : null,
            'billing_state' => isset($payload['billing_state']) ? sanitize_text_field((string) $payload['billing_state']) : null,
            'billing_postcode' => isset($payload['billing_postcode']) ? sanitize_text_field((string) $payload['billing_postcode']) : null,
            'billing_country' => isset($payload['billing_country']) ? sanitize_text_field((string) $payload['billing_country']) : null,
            'customer_type' => $customerType,
            'business_type' => isset($payload['business_type']) ? sanitize_text_field((string) $payload['business_type']) : null,
            'can_be_profiled' => isset($payload['can_be_profiled']) ? (int) (bool) $payload['can_be_profiled'] : null,
            'can_receive_marketing' => $requestedMarketing,
            'loyalty_enabled' => $loyaltyEnabled,
            'source' => 'admin',
        ];

        $targetEmail = $email ?? (string) $current->email;
        $espStatus = $this->get_esp_consent_status($targetEmail);
        if ($requestedMarketing !== null) {
            $this->maybe_update_esp_consent($targetEmail, $requestedMarketing, 'admin', null);
        }

        $updated = $this->update_customer_fields($customerId, $data, true);
        if (!$updated) {
            return [
                'ok' => false,
                'code' => 'customer_update_failed',
                'error' => 'No se pudo actualizar el cliente.',
            ];
        }

        if ($emailChanged) {
            $this->auditLogger->log('customer_email_changed', 'customer', $customerId, get_current_user_id(), [
                'old_email' => $this->mask_email((string) $current->email),
                'new_email' => $this->mask_email((string) $email),
            ]);
        }

        if ($typeChanged) {
            $this->auditLogger->log('customer_type_changed', 'customer', $customerId, get_current_user_id(), [
                'old' => (string) $current->customer_type,
                'new' => (string) $customerType,
            ]);
        }

        if ($requestedMarketing !== null) {
            $finalMarketing = $requestedMarketing;
            if ($espStatus === 'opt_out') {
                $finalMarketing = 0;
            }
            if ((int) $current->can_receive_marketing !== $finalMarketing) {
                $action = $finalMarketing === 1 ? 'customer_marketing_opt_in' : 'customer_marketing_opt_out';
                $this->auditLogger->log($action, 'customer', $customerId, get_current_user_id(), [
                    'source' => 'admin',
                ]);
            }
        }

        if ($loyaltyEnabled !== null && $this->loyalty_column_exists()) {
            $previous = (int) ($current->loyalty_enabled ?? 0);
            if ($previous !== $loyaltyEnabled) {
                $action = $loyaltyEnabled === 1 ? 'customer_loyalty_enabled' : 'customer_loyalty_disabled';
                $this->auditLogger->log($action, 'customer', $customerId, get_current_user_id(), [
                    'source' => 'admin',
                ]);
            }
        }

        return [
            'ok' => true,
        ];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function create_from_admin(array $payload): array
    {
        $email = isset($payload['email']) ? sanitize_email((string) $payload['email']) : '';
        if ($email === '') {
            return [
                'ok' => false,
                'code' => 'customer_email_invalid',
                'error' => 'Email inválido.',
            ];
        }

        $customerType = isset($payload['customer_type']) ? sanitize_text_field((string) $payload['customer_type']) : '';
        if (!in_array($customerType, ['b2c', 'b2b'], true)) {
            return [
                'ok' => false,
                'code' => 'customer_type_invalid',
                'error' => 'Tipo de cliente inválido.',
            ];
        }

        if ($this->get_customer_by_email_type($email, $customerType)) {
            return [
                'ok' => false,
                'code' => 'customer_duplicate',
                'error' => 'Ya existe un cliente con ese email y tipo.',
            ];
        }

        $canBeProfiled = isset($payload['can_be_profiled']) ? (int) (bool) $payload['can_be_profiled'] : 0;
        $canReceiveMarketing = isset($payload['can_receive_marketing']) ? (int) (bool) $payload['can_receive_marketing'] : 0;
        $loyaltyEnabled = $this->loyalty_column_exists()
            ? (int) (bool) ($payload['loyalty_enabled'] ?? 0)
            : null;

        $warning = null;
        $warningCode = null;
        $espStatus = $this->get_esp_consent_status($email);
        if ($espStatus === 'opt_out') {
            $canReceiveMarketing = 0;
            $warning = 'El cliente tiene opt-out en ESP. Marketing desactivado.';
            $warningCode = 'customer_marketing_forced_off';
        }

        $now = current_time('mysql');
        $data = [
            'email' => $email,
            'customer_type' => $customerType,
            'first_name' => isset($payload['first_name']) ? sanitize_text_field((string) $payload['first_name']) : null,
            'last_name' => isset($payload['last_name']) ? sanitize_text_field((string) $payload['last_name']) : null,
            'phone' => isset($payload['phone']) ? sanitize_text_field((string) $payload['phone']) : null,
            'company' => isset($payload['company']) ? sanitize_text_field((string) $payload['company']) : null,
            'business_type' => isset($payload['business_type']) ? sanitize_text_field((string) $payload['business_type']) : null,
            'can_be_profiled' => $canBeProfiled,
            'can_receive_marketing' => $canReceiveMarketing,
            'loyalty_enabled' => $loyaltyEnabled,
            'status' => 'active',
            'source' => 'admin',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';

        $inserted = $wpdb->insert($table, $this->filter_null_values($data));
        if (!$inserted) {
            return [
                'ok' => false,
                'code' => 'customer_create_failed',
                'error' => 'No se pudo crear el cliente.',
            ];
        }

        $customerId = (int) $wpdb->insert_id;

        $this->auditLogger->log('customer_created', 'customer', $customerId, get_current_user_id(), [
            'source' => 'admin',
            'email' => $this->mask_email($email),
            'customer_type' => $customerType,
        ]);

        if ($canReceiveMarketing === 1 && $espStatus !== 'opt_out') {
            $this->maybe_update_esp_consent($email, 1, 'admin', null);
            $this->auditLogger->log('customer_marketing_opt_in', 'customer', $customerId, get_current_user_id(), [
                'source' => 'admin',
            ]);
        }

        if ($loyaltyEnabled === 1) {
            $this->auditLogger->log('customer_loyalty_enabled', 'customer', $customerId, get_current_user_id(), [
                'source' => 'admin',
            ]);
        }

        $response = [
            'ok' => true,
            'customer_id' => $customerId,
        ];

        if ($warning !== null) {
            $response['warning'] = $warning;
            $response['warning_code'] = $warningCode;
        }

        return $response;
    }

    public function anonymize_customer(int $customerId): bool
    {
        $current = $this->get_customer($customerId);
        if (!$current) {
            return false;
        }

        if ((string) $current->status === 'anonymized') {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';

        $placeholderEmail = 'anon+' . $customerId . '@example.invalid';

        $updated = $wpdb->update(
            $table,
            [
                'email' => $placeholderEmail,
                'first_name' => null,
                'last_name' => null,
                'phone' => null,
                'company' => null,
                'billing_address_1' => null,
                'billing_address_2' => null,
                'billing_city' => null,
                'billing_state' => null,
                'billing_postcode' => null,
                'billing_country' => null,
                'can_be_profiled' => 0,
                'can_receive_marketing' => 0,
                'loyalty_enabled' => 0,
                'status' => 'anonymized',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $customerId],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return false;
        }

        $this->auditLogger->log('customer_anonymized', 'customer', $customerId, get_current_user_id(), [
            'source' => 'admin',
        ]);

        return true;
    }

    public function anonymize_customer_from_cron(int $customerId, int $retentionMonths, string $cutoff): bool
    {
        $current = $this->get_customer($customerId);
        if (!$current) {
            return false;
        }

        if ((string) $current->status === 'anonymized') {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';

        $placeholderEmail = 'anon+' . $customerId . '@example.invalid';

        $updated = $wpdb->update(
            $table,
            [
                'email' => $placeholderEmail,
                'first_name' => null,
                'last_name' => null,
                'phone' => null,
                'company' => null,
                'billing_address_1' => null,
                'billing_address_2' => null,
                'billing_city' => null,
                'billing_state' => null,
                'billing_postcode' => null,
                'billing_country' => null,
                'can_be_profiled' => 0,
                'can_receive_marketing' => 0,
                'loyalty_enabled' => 0,
                'status' => 'anonymized',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $customerId],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return false;
        }

        $this->auditLogger->log('customer_anonymized', 'customer', $customerId, null, [
            'source' => 'cron',
            'retention_months' => $retentionMonths,
            'cutoff' => $cutoff,
        ]);

        return true;
    }

    public function soft_delete_customer(int $customerId, ?int $actorId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $current = $this->get_customer($customerId);
        if (!$current) {
            return false;
        }

        if ((string) $current->status === 'deleted') {
            return true;
        }

        $updated = $wpdb->update(
            $table,
            [
                'status' => 'deleted',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $customerId],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return false;
        }

        $this->auditLogger->log('customer_deleted', 'customer', $customerId, $actorId, [
            'source' => 'admin',
        ]);

        return true;
    }

    public function restore_customer(int $customerId, ?int $actorId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $current = $this->get_customer($customerId);
        if (!$current) {
            return false;
        }

        if ((string) $current->status === 'active') {
            return true;
        }

        $updated = $wpdb->update(
            $table,
            [
                'status' => 'active',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $customerId],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return false;
        }

        $this->auditLogger->log('customer_restored', 'customer', $customerId, $actorId, [
            'source' => 'admin',
        ]);

        return true;
    }

    public function apply_loyalty_opt_in_from_order(int $customerId, object $order): void
    {
        if (!$this->is_order_opt_in($order, '_bressol_loyalty_opt_in')) {
            return;
        }

        if (!$this->loyalty_column_exists()) {
            return;
        }

        $customer = $this->get_customer($customerId);
        if (!$customer) {
            return;
        }

        if ((string) $customer->status !== 'active') {
            return;
        }

        if ((int) ($customer->loyalty_enabled ?? 0) === 1) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';

        $updated = $wpdb->update(
            $table,
            [
                'loyalty_enabled' => 1,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $customerId],
            ['%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return;
        }

        $proof = $this->build_checkout_proof($order);
        $this->auditLogger->log('customer_loyalty_opt_in', 'customer', $customerId, null, $proof);
        $this->add_customer_meta($customerId, 'loyalty_opt_in_proof', $proof);
    }

    public function apply_marketing_opt_in_from_order(int $customerId, object $order): void
    {
        if (!$this->is_order_opt_in($order, '_bressol_marketing_opt_in')) {
            return;
        }

        $customer = $this->get_customer($customerId);
        if (!$customer) {
            return;
        }

        if ((string) $customer->status !== 'active') {
            return;
        }

        $email = (string) $customer->email;
        if ($email === '') {
            return;
        }

        $espStatus = $this->get_esp_consent_status($email);
        if ($espStatus === 'opt_out') {
            return;
        }

        $orderId = $this->get_order_id($order);
        $this->maybe_update_esp_consent($email, 1, 'checkout', $orderId);
        $this->update_customer_fields($customerId, [
            'can_receive_marketing' => 1,
            'source' => 'checkout',
        ], false);

        $this->auditLogger->log('customer_marketing_opt_in', 'customer', $customerId, null, [
            'source' => 'checkout',
            'order_id' => $orderId,
        ]);
    }

    /** @return array<string, mixed> */
    public function get_export_data(int $customerId): array
    {
        $customer = $this->get_customer($customerId);
        if (!$customer) {
            return [];
        }

        global $wpdb;
        $ledgerTable = $wpdb->prefix . 'bressol_crm_points_ledger';
        $redemptionsTable = $wpdb->prefix . 'bressol_crm_points_redemptions';
        $auditTable = $wpdb->prefix . 'bressol_crm_audit_logs';
        $orderSyncTable = $wpdb->prefix . 'bressol_crm_order_sync';
        $metaTable = $wpdb->prefix . 'bressol_crm_customer_meta';
        $tagsTable = $wpdb->prefix . 'bressol_crm_tags';
        $customerTagsTable = $wpdb->prefix . 'bressol_crm_customer_tags';

        $pointsService = new PointsService(new Settings(), $this->auditLogger);
        $pointsBalance = $pointsService->get_balance($customerId);

        $ledger = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, source_type, source_id, points, status, earned_at, expires_at, expired_at, notes, created_at FROM {$ledgerTable} WHERE customer_id = %d ORDER BY earned_at DESC",
                $customerId
            ),
            ARRAY_A
        );

        $redemptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, redemption_type, points_used, reference, notes, created_at FROM {$redemptionsTable} WHERE customer_id = %d ORDER BY created_at DESC",
                $customerId
            ),
            ARRAY_A
        );

        $orderSync = [];
        if ($this->order_sync_table_exists($orderSyncTable)) {
            $orderSync = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT order_id, order_total, processed_at FROM {$orderSyncTable} WHERE customer_id = %d ORDER BY processed_at DESC",
                    $customerId
                ),
                ARRAY_A
            );
        }

        $meta = [];
        if ($this->table_exists($metaTable)) {
            $meta = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$metaTable} WHERE customer_id = %d ORDER BY id ASC",
                    $customerId
                ),
                ARRAY_A
            );
        }

        $tags = [];
        if ($this->table_exists($tagsTable) && $this->table_exists($customerTagsTable)) {
            $tags = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT t.id, t.name, t.slug, ct.created_at FROM {$customerTagsTable} ct INNER JOIN {$tagsTable} t ON t.id = ct.tag_id WHERE ct.customer_id = %d ORDER BY t.name ASC",
                    $customerId
                ),
                ARRAY_A
            );
        }

        $auditRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT action, context, created_at FROM {$auditTable} WHERE entity_type = %s AND entity_id = %d ORDER BY created_at DESC",
                'customer',
                $customerId
            ),
            ARRAY_A
        );

        $audit = [];
        foreach ($auditRows as $row) {
            $audit[] = [
                'action' => (string) $row['action'],
                'created_at' => (string) $row['created_at'],
                'context' => $this->filter_audit_context($row['context'] ?? null),
            ];
        }

        return [
            'customer' => $customer,
            'marketing_state' => $this->get_effective_marketing_state((string) $customer->email),
            'points_balance' => $pointsBalance,
            'points_ledger' => $ledger,
            'points_redemptions' => $redemptions,
            'order_sync' => $orderSync,
            'customer_meta' => $meta,
            'tags' => $tags,
            'audit' => $audit,
            'generated_at' => current_time('mysql'),
        ];
    }

    /** @return array<string, mixed> */
    public function get_marketing_flags_by_email(string $email): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT can_be_profiled, can_receive_marketing FROM {$table} WHERE email = %s LIMIT 1",
                $email
            )
        );

        if (!$row) {
            return [
                'can_be_profiled' => false,
                'can_receive_marketing' => false,
            ];
        }

        $espStatus = $this->get_esp_consent_status($email);
        if ($espStatus === 'opt_out') {
            return [
                'can_be_profiled' => (bool) $row->can_be_profiled,
                'can_receive_marketing' => false,
            ];
        }

        return [
            'can_be_profiled' => (bool) $row->can_be_profiled,
            'can_receive_marketing' => (bool) $row->can_receive_marketing,
        ];
    }

    /** @return array<string, mixed> */
    public function get_effective_marketing_state(string $email): array
    {
        global $wpdb;

        $crmFlags = [
            'can_be_profiled' => false,
            'can_receive_marketing' => false,
        ];

        if ($email !== '') {
            $table = $wpdb->prefix . 'bressol_crm_customers';
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT can_be_profiled, can_receive_marketing FROM {$table} WHERE email = %s LIMIT 1",
                    $email
                )
            );

            if ($row) {
                $crmFlags = [
                    'can_be_profiled' => (bool) $row->can_be_profiled,
                    'can_receive_marketing' => (bool) $row->can_receive_marketing,
                ];
            }
        }

        $espDetails = $email !== '' ? $this->get_esp_consent_details($email) : null;
        $espStatus = $espDetails['status'] ?? null;

        $effectiveFlags = [
            'can_be_profiled' => $crmFlags['can_be_profiled'],
            'can_receive_marketing' => $crmFlags['can_receive_marketing'],
        ];

        if ($espStatus === 'opt_out') {
            $effectiveFlags['can_receive_marketing'] = false;
        }

        return [
            'crm_flags' => $crmFlags,
            'esp_status' => $espStatus,
            'esp_details' => $espDetails,
            'effective_flags' => $effectiveFlags,
        ];
    }

    /** @return array<int, string> */
    public function list_marketing_emails(int $limit, int $offset): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $espTable = $wpdb->prefix . 'bressol_esp_consents';
        $espExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $espTable));

        if ($espExists === $espTable) {
            $sql = $wpdb->prepare(
                "SELECT c.email FROM {$table} c LEFT JOIN {$espTable} e ON c.email = e.email WHERE c.can_be_profiled = 1 AND c.can_receive_marketing = 1 AND (e.status IS NULL OR e.status != 'opt_out') ORDER BY c.updated_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT email FROM {$table} WHERE can_be_profiled = 1 AND can_receive_marketing = 1 ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        }

        $emails = $wpdb->get_col($sql);
        return array_map('strval', $emails);
    }

    public function find_customer_by_email_type(string $email, string $customerType): ?\stdClass
    {
        return $this->get_customer_by_email_type($email, $customerType);
    }

    public function is_loyalty_enabled(int $customerId): bool
    {
        $customer = $this->get_customer($customerId);
        if (!$customer) {
            return false;
        }

        if (!$this->loyalty_column_exists()) {
            return false;
        }

        if ((string) ($customer->status ?? '') !== 'active') {
            return false;
        }

        return (int) ($customer->loyalty_enabled ?? 0) === 1;
    }

    private function get_customer_by_email_type(string $email, string $customerType): ?\stdClass
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';

        $customer = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE email = %s AND customer_type = %s LIMIT 1",
                $email,
                $customerType
            )
        );

        return $customer ?: null;
    }

    private function loyalty_column_exists(): bool
    {
        if ($this->loyaltyColumnExists !== null) {
            return $this->loyaltyColumnExists;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $column = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'loyalty_enabled')
        );

        $this->loyaltyColumnExists = $column !== null;

        return $this->loyaltyColumnExists;
    }

    private function exists_customer_by_email_type_except_id(string $email, string $customerType, int $customerId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customers';
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE email = %s AND customer_type = %s AND id != %d LIMIT 1",
                $email,
                $customerType,
                $customerId
            )
        );

        return $existing !== null;
    }

    private function normalize_nullable_int($value): ?int
    {
        $value = (int) $value;
        if ($value <= 0) {
            return null;
        }

        return $value;
    }

    private function get_order_customer_id(object $order): ?int
    {
        if (method_exists($order, 'get_customer_id')) {
            return $this->normalize_nullable_int($order->get_customer_id());
        }

        return null;
    }

    private function get_order_user_id(object $order): ?int
    {
        if (method_exists($order, 'get_user_id')) {
            return $this->normalize_nullable_int($order->get_user_id());
        }

        return $this->get_order_customer_id($order);
    }

    private function has_order_been_processed(int $orderId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_order_sync';
        if (!$this->order_sync_table_exists($table)) {
            return false;
        }

        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT order_id FROM {$table} WHERE order_id = %d LIMIT 1", $orderId)
        );

        return $existing !== null;
    }

    private function mark_order_processed(int $orderId, int $customerId, float $total, string $processedAt): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_order_sync';
        if (!$this->order_sync_table_exists($table)) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'order_total' => $total,
                'processed_at' => $processedAt,
            ],
            ['%d', '%d', '%f', '%s']
        );
    }

    private function order_sync_table_exists(string $table): bool
    {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }

    private function maybe_update_esp_consent(string $email, int $marketingValue, string $source, ?int $orderId): bool
    {
        $service = $this->resolve_esp_consent_service();
        if (!$service instanceof EspConsentService) {
            return false;
        }

        $status = $marketingValue === 1 ? 'opt_in' : 'opt_out';
        $current = $this->get_esp_consent_status($email);
        if ($current === 'opt_out' && $status === 'opt_in') {
            return false;
        }

        return $service->set_consent_status($email, $status, $source, $orderId);
    }

    private function add_customer_meta(int $customerId, string $metaKey, array $metaValue): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bressol_crm_customer_meta';
        if (!$this->table_exists($table)) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'customer_id' => $customerId,
                'meta_key' => $metaKey,
                'meta_value' => wp_json_encode($metaValue),
            ],
            ['%d', '%s', '%s']
        );
    }

    private function is_order_opt_in(object $order, string $metaKey): bool
    {
        if (!method_exists($order, 'get_meta')) {
            return false;
        }

        $value = $order->get_meta($metaKey);
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower((string) $value);
        return in_array($normalized, ['yes', '1', 'true'], true);
    }

    private function build_checkout_proof(object $order): array
    {
        $orderId = $this->get_order_id($order);
        $timestamp = current_time('mysql');
        $ipAddress = null;
        $userAgent = null;

        if (method_exists($order, 'get_customer_ip_address')) {
            $ipAddress = sanitize_text_field((string) $order->get_customer_ip_address());
        }
        if (method_exists($order, 'get_customer_user_agent')) {
            $userAgent = sanitize_text_field((string) $order->get_customer_user_agent());
        }

        if ($ipAddress === '') {
            $ipAddress = null;
        }
        if ($userAgent === '') {
            $userAgent = null;
        }

        return [
            'source' => 'checkout',
            'order_id' => $orderId,
            'timestamp' => $timestamp,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
        ];
    }

    private function get_order_id(object $order): ?int
    {
        if (!method_exists($order, 'get_id')) {
            return null;
        }

        $orderId = (int) $order->get_id();
        if ($orderId <= 0) {
            return null;
        }

        return $orderId;
    }

    private function table_exists(string $table): bool
    {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }

    /** @param array<string, mixed> $data */
    private function filter_null_values(array $data): array
    {
        return array_filter(
            $data,
            static fn($value) => $value !== null
        );
    }

    private function get_esp_consent_status(string $email): ?string
    {
        $service = $this->resolve_esp_consent_service();
        if ($service instanceof EspConsentService) {
            return $service->get_consent_status($email);
        }

        return null;
    }

    /** @return array<string, string|null>|null */
    private function get_esp_consent_details(string $email): ?array
    {
        $service = $this->resolve_esp_consent_service();
        if ($service instanceof EspConsentService) {
            return $service->get_consent_details($email);
        }

        return null;
    }

    private function resolve_esp_consent_service(): ?EspConsentService
    {
        if ($this->espConsentService instanceof EspConsentService) {
            return $this->espConsentService;
        }

        if (!class_exists(EspConsentService::class)) {
            return null;
        }

        $this->espConsentService = new EspConsentService();

        return $this->espConsentService;
    }

    private function mask_email(string $email): string
    {
        if ($email === '') {
            return 'sin-email';
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return 'email-oculto';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $prefix = substr($local, 0, 2);
        if ($prefix === '') {
            $prefix = '*';
        }

        return $prefix . '***@' . $domain;
    }

    /** @param string|null $context */
    private function filter_audit_context(?string $context): ?array
    {
        if ($context === null || $context === '') {
            return null;
        }

        $decoded = json_decode($context, true);
        if (!is_array($decoded)) {
            return null;
        }

        $piiKeys = ['email', 'old_email', 'new_email', 'customer_email'];
        foreach ($piiKeys as $key) {
            if (array_key_exists($key, $decoded)) {
                return null;
            }
        }

        return $decoded;
    }
}
