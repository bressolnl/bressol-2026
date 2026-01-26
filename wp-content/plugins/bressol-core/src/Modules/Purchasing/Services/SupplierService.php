<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Repositories\SupplierRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class SupplierService
{
    private SupplierRepository $repository;
    private AuditLogger $auditLogger;

    public function __construct(SupplierRepository $repository, AuditLogger $auditLogger)
    {
        $this->repository = $repository;
        $this->auditLogger = $auditLogger;
    }

    /** @return array<int, array<string, mixed>> */
    public function list_suppliers(): array
    {
        return $this->repository->list_suppliers();
    }

    /** @param array<string, mixed> $payload */
    public function create_supplier(array $payload)
    {
        $data = $this->validate_and_normalize($payload);
        if ($data instanceof \WP_Error) {
            return $data;
        }

        $existing = $this->repository->get_supplier_by_code($data['supplier_code']);
        if ($existing) {
            return new \WP_Error('supplier_code_taken', 'Supplier code ya existe.');
        }

        $nowUtc = gmdate('Y-m-d H:i:s');
        $data['created_at_utc'] = $nowUtc;
        $data['updated_at_utc'] = $nowUtc;

        $newId = $this->repository->insert_supplier($data);
        if ($newId <= 0) {
            return new \WP_Error('supplier_insert_failed', 'No se pudo guardar.');
        }

        $this->auditLogger->log('supplier_created', [
            'supplier_id' => $newId,
            'supplier_code' => $data['supplier_code'],
        ], $newId, 'supplier');

        return $newId;
    }

    /** @param array<string, mixed> $payload */
    public function update_supplier(int $id, array $payload)
    {
        if ($id <= 0) {
            return new \WP_Error('supplier_invalid', 'Proveedor inválido.');
        }

        $existing = $this->repository->get_supplier($id);
        if (!$existing) {
            return new \WP_Error('supplier_not_found', 'Proveedor no encontrado.');
        }

        $data = $this->validate_and_normalize($payload);
        if ($data instanceof \WP_Error) {
            return $data;
        }

        $codeConflict = $this->repository->get_supplier_by_code($data['supplier_code']);
        if ($codeConflict && (int) $codeConflict['id'] !== $id) {
            return new \WP_Error('supplier_code_taken', 'Supplier code ya existe.');
        }

        $data['updated_at_utc'] = gmdate('Y-m-d H:i:s');

        $ok = $this->repository->update_supplier($id, $data);
        if (!$ok) {
            return new \WP_Error('supplier_update_failed', 'No se pudo actualizar.');
        }

        $this->auditLogger->log('supplier_updated', [
            'supplier_id' => $id,
            'supplier_code' => $data['supplier_code'],
        ], $id, 'supplier');

        return true;
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>|\WP_Error
     */
    private function validate_and_normalize(array $payload)
    {
        $codeRaw = isset($payload['supplier_code']) ? sanitize_text_field((string) $payload['supplier_code']) : '';
        $code = strtoupper(trim($codeRaw));
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{2,32}$/', $code)) {
            return new \WP_Error('supplier_code_invalid', 'Supplier code inválido.');
        }

        $nameRaw = isset($payload['name']) ? sanitize_text_field((string) $payload['name']) : '';
        $name = trim($nameRaw);
        if ($name === '' || strlen($name) < 2 || strlen($name) > 120) {
            return new \WP_Error('supplier_name_invalid', 'Nombre inválido.');
        }

        $leadRaw = isset($payload['lead_time_days']) ? sanitize_text_field((string) $payload['lead_time_days']) : '';
        $lead = $leadRaw === '' ? 0 : (int) $leadRaw;
        if ($lead < 0 || $lead > 365) {
            return new \WP_Error('supplier_lead_time_invalid', 'Lead time inválido.');
        }

        $minRaw = isset($payload['min_order_cents']) ? sanitize_text_field((string) $payload['min_order_cents']) : '';
        $min = null;
        if ($minRaw !== '') {
            $minValue = (int) $minRaw;
            if ($minValue < 0) {
                return new \WP_Error('supplier_moq_invalid', 'MOQ inválido.');
            }
            $min = $minValue;
        }

        $notesRaw = isset($payload['notes']) ? (string) $payload['notes'] : '';
        $notes = trim(wp_strip_all_tags($notesRaw));
        if (strlen($notes) > 2000) {
            $notes = substr($notes, 0, 2000);
        }

        return [
            'supplier_code' => $code,
            'name' => $name,
            'lead_time_days' => $lead,
            'min_order_cents' => $min,
            'notes' => $notes === '' ? null : $notes,
        ];
    }
}
