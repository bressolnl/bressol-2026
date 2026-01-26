<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Admin;

use Bressol\Modules\Purchasing\Services\Capabilities;

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

    private function handle_todo_action(string $nonceAction, string $page): void
    {
        if (!$this->capabilities->current_user_can_manage()) {
            $this->redirect_with_notice($page, 'forbidden');
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, $nonceAction)) {
            $this->redirect_with_notice($page, 'invalid_nonce');
        }

        $this->redirect_with_notice($page, 'todo');
    }

    private function redirect_with_notice(string $page, string $notice): void
    {
        $url = add_query_arg([
            'page' => $page,
            'purchasing_notice' => $notice,
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
