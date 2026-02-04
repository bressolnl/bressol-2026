<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerService
{
    private ?AuditLogger $auditLogger;

    public function __construct(?AuditLogger $auditLogger = null)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Devuelve un objeto simple compatible con lo que usa POS ahora.
     * (id, status, loyalty_enabled)
     */
    public function get_customer(int $customerId): ?object
    {
        if ($customerId <= 0) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';

        $row = $wpdb->get_row(
            $wpdb->prepare(
               "SELECT id, email, first_name, last_name, customer_type, status, loyalty_enabled, can_receive_marketing, can_be_profiled, city, source_event_id
                 FROM {$table} WHERE id = %d LIMIT 1",
                $customerId
            )
        );

        if (!$row) {
            return null;
        }

        // Normalizamos campos esperados.
        $obj = new \stdClass();
        $obj->id = (int) $row->id;
        $obj->email = (string) $row->email;
        $obj->first_name = (string) ($row->first_name ?? '');
        $obj->last_name = (string) ($row->last_name ?? '');
        $obj->customer_type = (string) ($row->customer_type ?? '');
        $obj->status = (string) $row->status;
        $obj->loyalty_enabled = (int) $row->loyalty_enabled;
        $obj->can_receive_marketing = (int) ($row->can_receive_marketing ?? 0);
        $obj->can_be_profiled = (int) ($row->can_be_profiled ?? 0);
        $obj->city = (string) ($row->city ?? '');
        $obj->source_event_id = (int) ($row->source_event_id ?? 0);

        return $obj;
    }

    public function is_loyalty_enabled(int $customerId): bool
    {
        $customer = $this->get_customer($customerId);
        if (!$customer) {
            return false;
        }
        return ((string) $customer->status === 'active') && ((int) $customer->loyalty_enabled === 1);
    }

    /**
     * Guarda datos básicos de cliente (insert/update).
     *
     * @param array<string, mixed> $data
     */
    public function save_customer(array $data): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';
        $now = current_time('mysql');

        $customerType = isset($data['customer_type']) ? (string) $data['customer_type'] : 'b2c';
        $email = isset($data['email']) ? sanitize_email((string) $data['email']) : '';
        if ($email === '') {
            return 0;
        }

        $payload = [
            'email' => $email,
            'customer_type' => $customerType !== '' ? $customerType : 'b2c',
            'first_name' => isset($data['first_name']) ? sanitize_text_field((string) $data['first_name']) : null,
            'last_name' => isset($data['last_name']) ? sanitize_text_field((string) $data['last_name']) : null,
            'status' => isset($data['status']) ? sanitize_text_field((string) $data['status']) : 'active',
            'loyalty_enabled' => isset($data['loyalty_enabled']) ? (int) $data['loyalty_enabled'] : 0,
            'can_receive_marketing' => isset($data['can_receive_marketing']) ? (int) $data['can_receive_marketing'] : 1,
            'can_be_profiled' => isset($data['can_be_profiled']) ? (int) $data['can_be_profiled'] : 1,
            'updated_at' => $now,
        ];

        $formats = ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s'];

        $customerId = isset($data['id']) ? (int) $data['id'] : 0;
        if ($customerId > 0) {
            $updated = $wpdb->update($table, $payload, ['id' => $customerId], $formats, ['%d']);
            return $updated === false ? 0 : $customerId;
        }

        $existingId = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE email = %s AND customer_type = %s LIMIT 1",
                $email,
                $payload['customer_type']
            )
        );

        if ($existingId > 0) {
            $updated = $wpdb->update($table, $payload, ['id' => $existingId], $formats, ['%d']);
            return $updated === false ? 0 : $existingId;
        }

        $payload['created_at'] = $now;
        $formats[] = '%s';

        $inserted = $wpdb->insert($table, $payload, $formats);
        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Devuelve flags de marketing efectivos para un email.
     *
     * @return array<string, mixed>
     */
    public function get_effective_marketing_state(string $email): array
    {
        $email = sanitize_email($email);
        if ($email === '') {
            return [
                'raw_flags' => [
                    'can_receive_marketing' => false,
                    'can_be_profiled' => false,
                ],
                'effective_flags' => [
                    'can_receive_marketing' => false,
                    'can_be_profiled' => false,
                ],
            ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status, can_receive_marketing, can_be_profiled
                 FROM {$table}
                 WHERE email = %s
                 ORDER BY id DESC
                 LIMIT 1",
                $email
            ),
            ARRAY_A
        );

        if (!$row) {
            return [
                'raw_flags' => [
                    'can_receive_marketing' => false,
                    'can_be_profiled' => false,
                ],
                'effective_flags' => [
                    'can_receive_marketing' => false,
                    'can_be_profiled' => false,
                ],
            ];
        }

        $rawCanReceive = ((int) ($row['can_receive_marketing'] ?? 0)) === 1;
        $rawCanProfile = ((int) ($row['can_be_profiled'] ?? 0)) === 1;
        $isActive = ((string) ($row['status'] ?? '')) === 'active';

        return [
            'customer_id' => (int) ($row['id'] ?? 0),
            'raw_flags' => [
                'can_receive_marketing' => $rawCanReceive,
                'can_be_profiled' => $rawCanProfile,
            ],
            'effective_flags' => [
                'can_receive_marketing' => $isActive && $rawCanReceive,
                'can_be_profiled' => $isActive && $rawCanProfile,
            ],
        ];
    }

    // --- Helpers “por si” los usa CustomerLookupService (POS) ---

    public function find_by_customer_id(int $customerId): ?object
    {
        return $this->get_customer($customerId);
    }

    public function find_by_public_id(string $token): ?object
    {
        // MVP: si todavía no existe token público en CRM, devolvemos null.
        // Más adelante: mapear token->customer_id en tabla/meta.
        return null;
    }
}
