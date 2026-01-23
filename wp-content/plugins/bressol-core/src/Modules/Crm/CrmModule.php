<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Crm\Admin\AdminPages;
use Bressol\Modules\Crm\Services\AuditLogger;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Crm\Services\ImportCustomersService;
use Bressol\Modules\Crm\Services\PointsService;
use Bressol\Modules\Crm\Services\Settings;
use Bressol\Modules\Crm\Services\TimelineService;
use Bressol\Modules\Esp\Services\EspConsentService;
use Bressol\Modules\Crm\Cli\ImportCustomersCommand;

if (!defined('ABSPATH')) {
    exit;
}

final class CrmModule implements ModuleInterface
{
    private const CRON_HOOK = 'bressol_crm_expire_points_daily';
    private const RETENTION_CRON_HOOK = 'bressol_crm_anonymize_customers';

    public function register(): void
    {
        Installer::maybe_upgrade();

        add_action(self::CRON_HOOK, [$this, 'runDailyExpiration']);
        add_action(self::RETENTION_CRON_HOOK, [$this, 'runRetentionAnonymization']);

        if (is_admin()) {
            $settings = new Settings();
            $auditLogger = new AuditLogger();
            $customerService = new CustomerService($auditLogger, $this->resolveEspConsentService());
            $pointsService = new PointsService($settings, $auditLogger);
            $importer = new ImportCustomersService($customerService, $pointsService);
            $timelineService = new TimelineService();

            $adminPages = new AdminPages($settings, $customerService, $pointsService, $auditLogger, $importer, $timelineService);
            add_action('admin_menu', [$adminPages, 'registerMenus']);
        }

        if (class_exists('WooCommerce')) {
            add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompleted'], 10, 1);
            add_action('woocommerce_order_status_processing', [$this, 'handleOrderCompleted'], 10, 1);
            add_action('woocommerce_order_refunded', [$this, 'handleOrderRefunded'], 10, 2);
        }

        if (defined('WP_CLI') && WP_CLI && class_exists('WooCommerce') && class_exists('\\WP_CLI')) {
            \WP_CLI::add_command('bressol crm import-customers', new ImportCustomersCommand(
                new ImportCustomersService(
                    new CustomerService(null, $this->resolveEspConsentService()),
                    new PointsService(new Settings(), new AuditLogger())
                )
            ));
        }
    }

    public function runDailyExpiration(): void
    {
        $pointsService = new PointsService(new Settings(), new AuditLogger());
        $pointsService->expire_points();
    }

    public function runRetentionAnonymization(): void
    {
        $settings = new Settings();
        $retentionMonths = $settings->get_retention_months();
        $customerService = new CustomerService(new AuditLogger(), $this->resolveEspConsentService());

        $cutoff = (new \DateTimeImmutable('now', wp_timezone()))
            ->modify('-' . $retentionMonths . ' months')
            ->format('Y-m-d H:i:s');

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customers';

        $sql = $wpdb->prepare(
            "SELECT id FROM {$table} WHERE status = 'active' AND last_order_at IS NOT NULL AND last_order_at < %s",
            $cutoff
        );

        $ids = $wpdb->get_col($sql);

        if (!$ids) {
            return;
        }

        foreach ($ids as $customerId) {
            $customerService->anonymize_customer_from_cron((int) $customerId, $retentionMonths, $cutoff);
        }
    }

    public function handleOrderCompleted(int $orderId): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        $auditLogger = new AuditLogger();
        $customerService = new CustomerService($auditLogger, $this->resolveEspConsentService());
        $settings = new Settings();
        $pointsService = new PointsService($settings, $auditLogger);

        $customerId = $customerService->upsert_from_order($order);
        if (!$customerId) {
            return;
        }

        $customerService->update_metrics_from_order($customerId, $order);
        $pointsService->award_points_for_order($customerId, $order, $customerService->get_customer_type($customerId));
    }

    public function handleOrderRefunded(int $orderId, int $refundId): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        $refund = wc_get_order($refundId);
        if (!$refund) {
            return;
        }

        $auditLogger = new AuditLogger();
        $customerService = new CustomerService($auditLogger, $this->resolveEspConsentService());
        $settings = new Settings();
        $pointsService = new PointsService($settings, $auditLogger);

        $customerId = $customerService->upsert_from_order($order);
        if (!$customerId) {
            return;
        }

        $customerType = $customerService->get_customer_type($customerId);
        $pointsService->register_refund_points($customerId, $refund, $customerType);
    }

    public static function schedule_cron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'daily', self::CRON_HOOK);
        }

        if (!wp_next_scheduled(self::RETENTION_CRON_HOOK)) {
            wp_schedule_event(time() + 600, 'weekly', self::RETENTION_CRON_HOOK);
        }
    }

    public static function clear_cron(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::RETENTION_CRON_HOOK);
    }

    private function resolveEspConsentService(): ?EspConsentService
    {
        if (class_exists(EspConsentService::class)) {
            return new EspConsentService();
        }

        return null;
    }
}
