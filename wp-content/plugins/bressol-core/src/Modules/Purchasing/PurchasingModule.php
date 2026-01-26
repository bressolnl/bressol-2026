<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Purchasing\Admin\Actions;
use Bressol\Modules\Purchasing\Admin\AdminPages;
use Bressol\Modules\Purchasing\Cli\SelfTestCommand;
use Bressol\Modules\Purchasing\Cron\PurchasingCron;
use Bressol\Modules\Purchasing\Repositories\PurchaseOrderRepository;
use Bressol\Modules\Purchasing\Repositories\ReceivingRepository;
use Bressol\Modules\Purchasing\Services\AuditLogger;
use Bressol\Modules\Purchasing\Services\Capabilities;
use Bressol\Modules\Purchasing\Services\CostLedgerSyncService;
use Bressol\Modules\Purchasing\Services\IdempotencyStore;
use Bressol\Modules\Purchasing\Services\Settings;
use Bressol\Modules\Purchasing\Services\StockSyncService;
use Bressol\Modules\Purchasing\Services\Ports\Adapters\CostLedgerAdapter;
use Bressol\Modules\Purchasing\Services\Ports\Adapters\WooStockAdapter;

if (!defined('ABSPATH')) {
    exit;
}

final class PurchasingModule implements ModuleInterface
{
    private Capabilities $capabilities;
    private Settings $settings;
    private AuditLogger $auditLogger;
    private PurchasingCron $cron;

    public function register(): void
    {
        $this->capabilities = new Capabilities();
        $this->settings = new Settings();
        $this->auditLogger = new AuditLogger();
        $this->cron = new PurchasingCron($this->settings, $this->auditLogger);

        add_action('admin_init', [Installer::class, 'maybe_upgrade']);
        add_action('admin_init', [Capabilities::class, 'ensure_caps_registered']);

        $this->cron->register();

        if (is_admin()) {
            $adminPages = new AdminPages($this->capabilities);
            $adminActions = new Actions($this->capabilities);
            add_action('admin_menu', [$adminPages, 'registerMenus']);
            $adminActions->register();
        }

        if (defined('WP_CLI') && WP_CLI && class_exists('\\WP_CLI')) {
            Installer::maybe_upgrade();
            \WP_CLI::add_command('bressol purchasing self-test', new SelfTestCommand(
                $this->capabilities,
                $this->settings
            ));
        }
    }

    public static function build_stock_sync_service(): StockSyncService
    {
        return new StockSyncService(
            new Settings(),
            new IdempotencyStore(),
            new ReceivingRepository(),
            new PurchaseOrderRepository(),
            new WooStockAdapter(),
            new AuditLogger()
        );
    }

    public static function build_cost_ledger_sync_service(): CostLedgerSyncService
    {
        return new CostLedgerSyncService(
            new Settings(),
            new IdempotencyStore(),
            new PurchaseOrderRepository(),
            new CostLedgerAdapter(),
            new AuditLogger()
        );
    }
}
