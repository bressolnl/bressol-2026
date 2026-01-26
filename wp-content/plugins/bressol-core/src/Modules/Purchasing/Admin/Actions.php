<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Admin;

use Bressol\Modules\Purchasing\Repositories\SupplierRepository;
use Bressol\Modules\Purchasing\Services\Capabilities;
use Bressol\Modules\Purchasing\Services\AuditLogger;
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
}
