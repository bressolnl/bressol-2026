<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Inventory\Admin\AdminPages;
use Bressol\Modules\Inventory\Cli\SelfTestCommand;
use Bressol\Modules\Inventory\Repositories\PackDefinitionRepository;
use Bressol\Modules\Inventory\Services\AuditLogger;
use Bressol\Modules\Inventory\Services\CacheService;
use Bressol\Modules\Inventory\Services\SellableService;
use Bressol\Modules\Inventory\Support\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

final class InventoryModule implements ModuleInterface
{
    private Capabilities $capabilities;
    private CacheService $cacheService;
    private AuditLogger $auditLogger;
    private SellableService $sellableService;

    public function register(): void
    {
        $this->capabilities = new Capabilities();
        $this->cacheService = new CacheService();
        $this->auditLogger = new AuditLogger();
        $this->sellableService = SellableService::build_default($this->cacheService, $this->auditLogger);

        if (is_admin()) {
            $adminPages = new AdminPages($this->capabilities, $this->sellableService);
            add_action('admin_menu', [$adminPages, 'registerMenus']);
        }

        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateAddToCart'], 20, 3);
        add_action('woocommerce_checkout_process', [$this, 'validateCheckout']);

        add_action('woocommerce_product_set_stock', [$this, 'handleProductStockChanged']);
        add_action('woocommerce_product_set_stock_status', [$this, 'handleProductStockChanged']);
        add_action('updated_post_meta', [$this, 'handlePackMetaChanged'], 10, 4);
        add_action('added_post_meta', [$this, 'handlePackMetaChanged'], 10, 4);
        add_action('deleted_post_meta', [$this, 'handlePackMetaChanged'], 10, 4);

        if (defined('WP_CLI') && WP_CLI && class_exists('\\WP_CLI')) {
            \WP_CLI::add_command('bressol inventory selftest', new SelfTestCommand());
        }
    }

    public function validateAddToCart(bool $passed, int $productId, int $qty): bool
    {
        if (!$passed) {
            return false;
        }

        $selection = null;
        if ($this->sellableService->is_pack_product($productId)) {
            $selection = $this->sellableService->extract_pack_selection_from_request($productId, $_POST);
        }

        if ($this->sellableService->is_sellable($productId, $qty, $selection)) {
            return true;
        }

        $this->sellableService->log_blocked('web_add_to_cart', $productId, $qty, $selection);
        $message = $this->sellableService->build_unavailable_message($productId, $selection);
        wc_add_notice($message, 'error');

        return false;
    }

    public function validateCheckout(): void
    {
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        $cartItems = WC()->cart->get_cart();
        if (empty($cartItems)) {
            return;
        }

        foreach ($cartItems as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if ($productId <= 0) {
                continue;
            }

            $qty = isset($item['quantity']) ? (int) $item['quantity'] : 1;
            $selection = null;
            if ($this->sellableService->is_pack_product($productId)) {
                $selection = $this->sellableService->extract_pack_selection_from_cart_item($item);
            }

            if (!$this->sellableService->is_sellable($productId, $qty, $selection)) {
                $this->sellableService->log_blocked('web_checkout', $productId, $qty, $selection);
                $message = $this->sellableService->build_unavailable_message($productId, $selection);
                wc_add_notice($message, 'error');
                return;
            }
        }
    }

    public function handleProductStockChanged($product): void
    {
        $this->cacheService->bump_version();
    }

    public function handlePackMetaChanged($metaId, int $postId, string $metaKey, $metaValue): void
    {
        if ($metaKey !== PackDefinitionRepository::META_KEY) {
            return;
        }

        $this->cacheService->bump_version();
    }
}
