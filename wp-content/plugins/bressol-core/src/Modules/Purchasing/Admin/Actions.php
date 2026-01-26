<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Admin;

use Bressol\Modules\Purchasing\Repositories\SupplierRepository;
use Bressol\Modules\Purchasing\Services\Capabilities;
use Bressol\Modules\Purchasing\Services\AuditLogger;
use Bressol\Modules\Purchasing\Services\Ports\NullAdapters\NullCostLedgerWritePort;
use Bressol\Modules\Purchasing\Repositories\PurchaseOrderRepository;
use Bressol\Modules\Purchasing\Repositories\ReceivingRepository;
use Bressol\Modules\Purchasing\PurchasingModule;
use Bressol\Modules\Purchasing\Services\PurchaseOrderService;
use Bressol\Modules\Purchasing\Services\ReceivingService;
use Bressol\Modules\Purchasing\Services\SupplierService;

if (!defined('ABSPATH')) {
    exit;
}

final class Actions
{
    private Capabilities $capabilities;

    public function __construct(Capabilities $capabilities)
    {
        $this->capabilities = $capabilities;
    }

    public function register(): void
    {
        add_action('admin_post_bressol_purchasing_add_supplier', [$this, 'handle_add_supplier']);
        add_action('admin_post_bressol_purchasing_add_purchase_order', [$this, 'handle_add_purchase_order']);
        add_action('admin_post_bressol_purchasing_add_receiving', [$this, 'handle_add_receiving']);
        add_action('admin_post_bressol_purchasing_save_supplier', [$this, 'handle_save_supplier']);
        add_action('admin_post_bressol_purchasing_save_po', [$this, 'handle_save_po']);
        add_action('admin_post_bressol_purchasing_change_po_status', [$this, 'handle_change_po_status']);
        add_action('admin_post_bressol_purchasing_create_receiving', [$this, 'handle_create_receiving']);
    }

    public function handle_add_supplier(): void
    {
        $this->handle_todo_action('bressol_purchasing_add_supplier_nonce', 'bressol-purchasing');
    }

    public function handle_add_purchase_order(): void
    {
        $this->handle_todo_action('bressol_purchasing_add_purchase_order_nonce', 'bressol-purchasing-pos');
    }

    public function handle_add_receiving(): void
    {
        $this->handle_todo_action('bressol_purchasing_add_receiving_nonce', 'bressol-purchasing-receivings');
    }

    public function handle_save_supplier(): void
    {
        if (!$this->capabilities->current_user_can_sensitive()) {
            $this->redirect_with_notice('bressol-purchasing', 'forbidden');
        }

        check_admin_referer('bressol_purchasing_save_supplier');

        $supplierId = isset($_POST['supplier_id']) ? absint($_POST['supplier_id']) : 0;
        $payload = [
            'supplier_code' => isset($_POST['supplier_code']) ? wp_unslash($_POST['supplier_code']) : '',
            'name' => isset($_POST['name']) ? wp_unslash($_POST['name']) : '',
            'lead_time_days' => isset($_POST['lead_time_days']) ? wp_unslash($_POST['lead_time_days']) : '',
            'min_order_cents' => isset($_POST['min_order_cents']) ? wp_unslash($_POST['min_order_cents']) : '',
            'notes' => isset($_POST['notes']) ? wp_unslash($_POST['notes']) : '',
        ];

        $service = new SupplierService(new SupplierRepository(), new AuditLogger());
        if ($supplierId > 0) {
            $result = $service->update_supplier($supplierId, $payload);
        } else {
            $result = $service->create_supplier($payload);
            if (is_int($result) && $result > 0) {
                $supplierId = $result;
            }
        }

        if ($result instanceof \WP_Error) {
            $this->redirect_with_notice('bressol-purchasing', 'supplier_save_failed', $this->supplier_form_args($supplierId));
        }

        $this->redirect_with_notice('bressol-purchasing', 'supplier_saved');
    }

    public function handle_save_po(): void
    {
        if (!$this->capabilities->current_user_can_sensitive()) {
            $this->redirect_with_notice('bressol-purchasing-pos', 'forbidden');
        }

        check_admin_referer('bressol_purchasing_save_po');

        $poId = isset($_POST['po_id']) ? absint($_POST['po_id']) : 0;
        $newStatus = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : '';
        $header = [
            'supplier_id' => isset($_POST['supplier_id']) ? absint($_POST['supplier_id']) : 0,
            'po_number' => isset($_POST['po_number']) ? wp_unslash($_POST['po_number']) : '',
            'customs_fees_cents' => $this->parse_eur_to_cents(isset($_POST['customs_fees_eur']) ? wp_unslash($_POST['customs_fees_eur']) : '') ?? 0,
            'tax_rate_bp' => isset($_POST['tax_rate_bp']) ? wp_unslash($_POST['tax_rate_bp']) : '',
            'warehouse_code' => isset($_POST['warehouse_code']) ? wp_unslash($_POST['warehouse_code']) : '',
            'status' => isset($_POST['status']) ? wp_unslash($_POST['status']) : 'draft',
        ];

        $lines = $this->parse_po_lines($_POST);
        $receivingRepository = new ReceivingRepository();
        $allowLineUpdate = $poId <= 0 || $receivingRepository->count_receivings_for_po($poId) === 0;

        $service = new PurchaseOrderService(
            new PurchaseOrderRepository(),
            new SupplierRepository(),
            $receivingRepository,
            new NullCostLedgerWritePort(),
            new AuditLogger(),
            PurchasingModule::build_cost_ledger_sync_service()
        );
        $result = $service->create_or_update_po($poId > 0 ? $poId : null, $header, $lines, $allowLineUpdate);

        if ($result instanceof \WP_Error) {
            $this->redirect_with_notice('bressol-purchasing-pos', 'po_save_failed', $this->po_form_args($poId));
        }

        $poId = is_int($result) ? $result : $poId;

        if ($newStatus !== '' && $poId > 0) {
            $statusResult = $service->change_status($poId, $newStatus);
            if ($statusResult instanceof \WP_Error) {
                $this->redirect_with_notice('bressol-purchasing-pos', 'po_status_failed', $this->po_form_args($poId));
            }
            $this->redirect_with_notice('bressol-purchasing-pos', 'po_status_changed', $this->po_form_args($poId));
        }

        $this->redirect_with_notice('bressol-purchasing-pos', 'po_saved', $this->po_form_args($poId));
    }

    public function handle_change_po_status(): void
    {
        if (!$this->capabilities->current_user_can_sensitive()) {
            $this->redirect_with_notice('bressol-purchasing-pos', 'forbidden');
        }

        check_admin_referer('bressol_purchasing_change_po_status');

        $poId = isset($_POST['po_id']) ? absint($_POST['po_id']) : 0;
        $newStatus = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : '';

        $service = new PurchaseOrderService(
            new PurchaseOrderRepository(),
            new SupplierRepository(),
            new ReceivingRepository(),
            new NullCostLedgerWritePort(),
            new AuditLogger(),
            PurchasingModule::build_cost_ledger_sync_service()
        );
        $result = $service->change_status($poId, $newStatus);

        if ($result instanceof \WP_Error) {
            $this->redirect_with_notice('bressol-purchasing-pos', 'po_status_failed', $this->po_form_args($poId));
        }

        $this->redirect_with_notice('bressol-purchasing-pos', 'po_status_changed', $this->po_form_args($poId));
    }

    public function handle_create_receiving(): void
    {
        if (!$this->capabilities->current_user_can_sensitive()) {
            $this->redirect_with_notice('bressol-purchasing-receivings', 'forbidden');
        }

        check_admin_referer('bressol_purchasing_create_receiving');

        $poId = isset($_POST['po_id']) ? absint($_POST['po_id']) : 0;
        $receivedAt = isset($_POST['received_at']) ? wp_unslash($_POST['received_at']) : '';
        $receivedAtUtc = $this->parse_received_at_utc(is_string($receivedAt) ? $receivedAt : '');
        if ($receivedAtUtc === false) {
            $this->redirect_with_notice('bressol-purchasing-receivings', 'receiving_save_failed', ['po_id' => $poId, 'view' => 'add']);
        }

        $note = isset($_POST['note']) ? wp_unslash($_POST['note']) : '';
        $lines = $this->parse_receiving_lines($_POST);

        $service = new ReceivingService(
            new ReceivingRepository(),
            new PurchaseOrderRepository(),
            new AuditLogger(),
            PurchasingModule::build_stock_sync_service()
        );
        $result = $service->create_receiving($poId, $receivedAtUtc ?: null, is_string($note) ? $note : null, $lines);

        if ($result instanceof \WP_Error) {
            $this->redirect_with_notice('bressol-purchasing-receivings', 'receiving_save_failed', ['po_id' => $poId, 'view' => 'add']);
        }

        $this->redirect_with_notice('bressol-purchasing-pos', 'receiving_saved', ['view' => 'edit', 'po_id' => $poId]);
    }

    private function handle_todo_action(string $nonceAction, string $page): void
    {
        if (!$this->capabilities->current_user_can_sensitive()) {
            $this->redirect_with_notice($page, 'forbidden');
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, $nonceAction)) {
            $this->redirect_with_notice($page, 'invalid_nonce');
        }

        $this->redirect_with_notice($page, 'todo');
    }

    private function redirect_with_notice(string $page, string $notice, array $extraArgs = []): void
    {
        $args = array_merge([
            'page' => $page,
            'purchasing_notice' => $notice,
        ], $extraArgs);
        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private function supplier_form_args(int $supplierId): array
    {
        if ($supplierId <= 0) {
            return [
                'view' => 'edit',
            ];
        }

        return [
            'view' => 'edit',
            'supplier_id' => $supplierId,
        ];
    }

    private function po_form_args(int $poId): array
    {
        if ($poId <= 0) {
            return [
                'view' => 'edit',
            ];
        }

        return [
            'view' => 'edit',
            'po_id' => $poId,
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<int, array<string, mixed>>
     */
    private function parse_po_lines(array $input): array
    {
        $skus = isset($input['line_sku']) ? (array) $input['line_sku'] : [];
        $qtys = isset($input['line_qty']) ? (array) $input['line_qty'] : [];
        $costs = isset($input['line_unit_cost_eur']) ? (array) $input['line_unit_cost_eur'] : [];

        $lines = [];
        $count = max(count($skus), count($qtys), count($costs));
        for ($i = 0; $i < $count; $i++) {
            $sku = isset($skus[$i]) ? wp_unslash($skus[$i]) : '';
            $qtyRaw = isset($qtys[$i]) ? wp_unslash($qtys[$i]) : '';
            $costRaw = isset($costs[$i]) ? wp_unslash($costs[$i]) : '';

            $qty = is_numeric($qtyRaw) ? (int) $qtyRaw : 0;
            $costCents = $this->parse_eur_to_cents($costRaw);

            if ($qty <= 0 && $costCents === null && trim((string) $sku) === '') {
                continue;
            }

            $lines[] = [
                'sku' => $sku,
                'qty' => $qty,
                'unit_cost_excl_tax_cents' => $costCents === null ? -1 : $costCents,
            ];
        }

        return $lines;
    }

    private function parse_eur_to_cents($raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = is_string($raw) ? trim($raw) : (string) $raw;
        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.]/', '', $value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        if ($float < 0) {
            return null;
        }

        return (int) round($float * 100);
    }

    /** @param array<string, mixed> $input
     *  @return array<int, array<string, mixed>>
     */
    private function parse_receiving_lines(array $input): array
    {
        $lineIds = isset($input['receiving_po_line_id']) ? (array) $input['receiving_po_line_id'] : [];
        $qtys = isset($input['receiving_qty']) ? (array) $input['receiving_qty'] : [];

        $lines = [];
        $count = max(count($lineIds), count($qtys));
        for ($i = 0; $i < $count; $i++) {
            $lineId = isset($lineIds[$i]) ? absint($lineIds[$i]) : 0;
            $qtyRaw = isset($qtys[$i]) ? wp_unslash($qtys[$i]) : '';
            $qty = is_numeric($qtyRaw) ? (int) $qtyRaw : 0;
            if ($lineId <= 0 || $qty <= 0) {
                continue;
            }
            $lines[] = [
                'po_line_id' => $lineId,
                'qty_received' => $qty,
            ];
        }

        return $lines;
    }

    private function parse_received_at_utc(string $input)
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $input, $tz);
        if (!$date) {
            return false;
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
