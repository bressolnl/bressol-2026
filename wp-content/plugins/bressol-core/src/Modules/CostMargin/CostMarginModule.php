<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\CostMargin\Admin\AdminPages;
use Bressol\Modules\CostMargin\Admin\Actions;
use Bressol\Modules\CostMargin\Cli\CogsCommand;
use Bressol\Modules\CostMargin\Cli\CostCommand;
use Bressol\Modules\CostMargin\Cli\MarginEvalCommand;
use Bressol\Modules\CostMargin\Cli\MarginPersistCommand;
use Bressol\Modules\CostMargin\Cli\MarginBackfillCommand;
use Bressol\Modules\CostMargin\Cli\MarginSelfTestCommand;
use Bressol\Modules\CostMargin\Services\MarginRulesService;
use Bressol\Modules\CostMargin\Services\MarginAuditService;
use Bressol\Modules\CostMargin\Services\OrderCogsFinalizer;
use Bressol\Modules\CostMargin\Transport\Installer;
use Bressol\Modules\CostMargin\Transport\Cli\TransportSnapshotCommand;
use Bressol\Modules\Inventory\Repositories\PackDefinitionRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class CostMarginModule implements ModuleInterface
{
    public function register(): void
    {
        add_action('admin_init', [Installer::class, 'maybe_upgrade']);
        add_action('admin_menu', [$this, 'registerAdminMenus']);

        (new Actions())->register();

        if (defined('WP_CLI') && WP_CLI) {
            Installer::maybe_upgrade();
        }

        add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompleted'], 10, 1);
        add_action('woocommerce_order_refunded', [$this, 'handleOrderRefunded'], 10, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'handleCheckoutProcessed'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateAddToCartMargin'], 30, 3);

        if (defined('WP_CLI') && WP_CLI && class_exists('\\WP_CLI')) {
            \WP_CLI::add_command('bressol cost', new CostCommand());
            \WP_CLI::add_command('bressol margin eval', new MarginEvalCommand());
            \WP_CLI::add_command('bressol margin persist', new MarginPersistCommand());
            \WP_CLI::add_command('bressol margin backfill', new MarginBackfillCommand());
            \WP_CLI::add_command('bressol margin selftest', new MarginSelfTestCommand());
            \WP_CLI::add_command('bressol cogs', new CogsCommand());
            \WP_CLI::add_command('bressol transport snapshot', new TransportSnapshotCommand());
        }
    }

    public function handleOrderCompleted(int $orderId): void
    {
        (new OrderCogsFinalizer())->handle_order_completed($orderId);
    }

    public function handleOrderRefunded(int $orderId, int $refundId): void
    {
        (new OrderCogsFinalizer())->handle_order_refunded($orderId, $refundId);
    }

    public function handleCheckoutProcessed(int $orderId, array $postedData, ?\WC_Order $order = null): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }
        if (!$order instanceof \WC_Order) {
            $order = wc_get_order($orderId);
        }
        if (!$order instanceof \WC_Order) {
            return;
        }

        try {
            (new MarginAuditService())->evaluate_and_persist_order($order, [
                'channel' => 'online',
                'market_cost_cents' => 0,
                'price_source' => 'woo_fallback',
            ]);
        } catch (\Throwable $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[bressol_margin] checkout_audit_failed order=' . $orderId);
            }
        }
    }

    public function registerAdminMenus(): void
    {
        (new AdminPages())->registerMenus();
    }

    public function validateAddToCartMargin(bool $passed, int $productId, int $qty): bool
    {
        if (!$passed || $productId <= 0 || $qty <= 0) {
            return $passed;
        }
        if (!function_exists('wc_get_product')) {
            return $passed;
        }

        $product = wc_get_product($productId);
        if (!$product instanceof \WC_Product) {
            return $passed;
        }

        $priceExclCents = 0;
        if (function_exists('wc_get_price_excluding_tax')) {
            $priceExcl = wc_get_price_excluding_tax($product, ['qty' => 1]);
            $priceExclCents = (int) round(((float) $priceExcl) * 100);
        } else {
            $priceExclCents = (int) round(((float) $product->get_price()) * 100);
        }

        $packSelection = null;
        $packRepo = new PackDefinitionRepository();
        if ($packRepo->is_pack_product($productId)) {
            $input = $_POST['bressol_pack'] ?? ($_POST['bressol_pack_config'] ?? $_POST);
            if (is_array($input)) {
                $selection = $packRepo->normalize_selection_from_request($productId, $input);
                $packSelection = $selection !== [] ? $selection : null;
            }
        }

        $rulesService = new MarginRulesService();
        $result = $rulesService->evaluate_cart([[
            'product_id' => $productId,
            'qty' => $qty,
            'price_excl_tax_cents' => $priceExclCents,
            'pack_selection' => $packSelection,
        ]], [
            'channel' => 'online',
            'market_cost_cents' => 0,
        ]);

        $computed = isset($result['computed']) && is_array($result['computed']) ? $result['computed'] : [];
        $missingCost = !empty($computed['missing_cost']);
        $status = (string) ($result['status'] ?? 'pass');

        if ($missingCost) {
            wc_add_notice('Costes incompletos. No se puede añadir al carrito.', 'error');
            return false;
        }
        if ($status !== 'pass') {
            wc_add_notice('Margen insuficiente. No se puede añadir al carrito.', 'error');
            return false;
        }

        return true;
    }
}
