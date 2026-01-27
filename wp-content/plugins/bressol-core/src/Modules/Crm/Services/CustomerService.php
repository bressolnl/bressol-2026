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
                "SELECT id, status, loyalty_enabled FROM {$table} WHERE id = %d LIMIT 1",
                $customerId
            )
        );

        if (!$row) {
            return null;
        }

        // Normalizamos campos esperados.
        $obj = new \stdClass();
        $obj->id = (int) $row->id;
        $obj->status = (string) $row->status;
        $obj->loyalty_enabled = (int) $row->loyalty_enabled;

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
