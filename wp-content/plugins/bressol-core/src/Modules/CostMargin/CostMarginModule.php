<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\CostMargin\Admin\AdminPages;
use Bressol\Modules\CostMargin\Cli\CogsCommand;
use Bressol\Modules\CostMargin\Cli\CostCommand;
use Bressol\Modules\CostMargin\Cli\MarginEvalCommand;
use Bressol\Modules\CostMargin\Cli\MarginPersistCommand;
use Bressol\Modules\CostMargin\Cli\MarginBackfillCommand;
use Bressol\Modules\CostMargin\Cli\MarginSelfTestCommand;
use Bressol\Modules\CostMargin\Services\MarginAuditService;
use Bressol\Modules\CostMargin\Services\OrderCogsFinalizer;
use Bressol\Modules\CostMargin\Transport\Installer;
use Bressol\Modules\CostMargin\Transport\Cli\TransportSnapshotCommand;

if (!defined('ABSPATH')) {
    exit;
}

final class CostMarginModule implements ModuleInterface
{
    public function register(): void
    {
        add_action('admin_init', [Installer::class, 'maybe_upgrade']);
        add_action('admin_menu', [$this, 'registerAdminMenus']);

        if (defined('WP_CLI') && WP_CLI) {
            Installer::maybe_upgrade();
        }

        add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompleted'], 10, 1);
        add_action('woocommerce_order_refunded', [$this, 'handleOrderRefunded'], 10, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'handleCheckoutProcessed'], 10, 3);

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
}
