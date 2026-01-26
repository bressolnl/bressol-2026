<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Crm\Admin\AdminPages;
use Bressol\Modules\Crm\Services\AuditLogger;
use Bressol\Modules\Crm\Services\Capabilities;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Crm\Services\ImportCustomersService;
use Bressol\Modules\Crm\Services\PointsService;
use Bressol\Modules\Crm\Services\Settings;
use Bressol\Modules\Crm\Services\TimelineService;
use Bressol\Modules\Esp\Services\EspConsentService;
use Bressol\Modules\Crm\Cli\ImportCustomersCommand;
use Bressol\Modules\Crm\Cli\SelfTestCommand;

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
        (new Capabilities())->register();

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
            add_action('admin_init', [$this, 'debugAdminAccess']);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                add_filter('user_has_cap', [$this, 'debugBypassAccess'], 10, 4);
            }
        }

        if (class_exists('WooCommerce')) {
            add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompleted'], 10, 1);
            add_action('woocommerce_order_status_processing', [$this, 'handleOrderCompleted'], 10, 1);
            add_action('woocommerce_order_refunded', [$this, 'handleOrderRefunded'], 10, 2);
            add_filter('woocommerce_checkout_fields', [$this, 'addCheckoutFields']);
            add_action('woocommerce_checkout_update_order_meta', [$this, 'saveCheckoutOptins'], 10, 1);
        }

        if (defined('WP_CLI') && WP_CLI && class_exists('WooCommerce') && class_exists('\\WP_CLI')) {
            $auditLogger = new AuditLogger();
            $settings = new Settings();
            $espService = $this->resolveEspConsentService();
            $customerService = new CustomerService($auditLogger, $espService);
            $pointsService = new PointsService($settings, $auditLogger);
            \WP_CLI::add_command('bressol crm import-customers', new ImportCustomersCommand(
                new ImportCustomersService($customerService, $pointsService)
            ));
            \WP_CLI::add_command('bressol crm selftest', new SelfTestCommand(
                $customerService,
                $pointsService,
                $espService
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

        $isPosOrder = $this->is_pos_order($order);
        $posCustomerId = $isPosOrder ? $this->get_pos_customer_id($order) : 0;

        $customerId = $posCustomerId > 0
            ? $posCustomerId
            : $customerService->upsert_from_order($order);
        if (!$customerId) {
            return;
        }
        
        $customer = $customerService->get_customer($customerId);
        if (!$customer || (string) $customer->status === 'deleted') {
            return;
        }

        $customerService->update_metrics_from_order($customerId, $order);
        $customerService->apply_loyalty_opt_in_from_order($customerId, $order);
        $customerService->apply_marketing_opt_in_from_order($customerId, $order);
        if ($customerService->is_loyalty_enabled($customerId)) {
            $pointsService->award_points_for_order($customerId, $order, $customerService->get_customer_type($customerId));
            return;
        }

        if ($isPosOrder && (string) $customer->status === 'active' && !$this->has_loyalty_opt_in($order)) {
            $auditLogger->log('points_skipped_not_enrolled', 'order', $orderId, null, [
                'source' => 'pos',
                'order_id' => $orderId,
                'customer_id' => $customerId,
            ]);
        }
    }

    public function debugAdminAccess(): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (!$this->isCrmAdminPage()) {
            return;
        }

        $user = wp_get_current_user();
        $roles = $user ? $user->roles : [];
        $cap = Capabilities::CAP;

        error_log('[Bressol CRM] Access debug: ' . wp_json_encode([
            'user_id' => $user ? $user->ID : 0,
            'roles' => $roles,
            'cap' => $cap,
            'current_user_can_cap' => current_user_can($cap),
            'current_user_can_manage_options' => current_user_can('manage_options'),
            'current_user_can_manage_woocommerce' => current_user_can('manage_woocommerce'),
        ]));
    }

    /**
     * TODO: remove debug bypass once caps are fixed.
     *
     * @param array<string, bool> $allcaps
     * @param array<int, string> $caps
     * @param array<int, mixed> $args
     */
    public function debugBypassAccess(array $allcaps, array $caps, array $args, \WP_User $user): array
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return $allcaps;
        }

        if (!$this->isCrmAdminPage()) {
            return $allcaps;
        }

        if (!$user->exists() || !in_array('administrator', $user->roles, true)) {
            return $allcaps;
        }

        $allcaps[Capabilities::CAP] = true;
        return $allcaps;
    }

    private function isCrmAdminPage(): bool
    {
        if (!is_admin() || !isset($_GET['page'])) {
            return false;
        }

        $page = sanitize_key(wp_unslash($_GET['page']));
        return str_starts_with($page, 'bressol_crm');
    }

    private function is_pos_order(object $order): bool
    {
        if (!method_exists($order, 'get_meta')) {
            return false;
        }

        return (string) $order->get_meta('_bressol_pos_channel') === 'pos';
    }

    private function get_pos_customer_id(object $order): int
    {
        if (!method_exists($order, 'get_meta')) {
            return 0;
        }

        return (int) $order->get_meta('_bressol_pos_customer_id');
    }

    private function has_loyalty_opt_in(object $order): bool
    {
        if (!method_exists($order, 'get_meta')) {
            return false;
        }

        $value = $order->get_meta('_bressol_loyalty_opt_in');
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower((string) $value);
        return in_array($normalized, ['yes', '1', 'true'], true);
    }

    /** @param array<string, mixed> $fields */
    public function addCheckoutFields(array $fields): array
    {
        $fields['billing']['bressol_loyalty_opt_in'] = [
            'type' => 'checkbox',
            'label' => 'Quiero unirme al programa de puntos',
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => 120,
        ];

        $fields['billing']['bressol_marketing_opt_in'] = [
            'type' => 'checkbox',
            'label' => 'Quiero recibir comunicaciones comerciales',
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => 121,
        ];

        return $fields;
    }

    public function saveCheckoutOptins(int $orderId): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        $loyaltyOptIn = isset($_POST['bressol_loyalty_opt_in']) ? (bool) wp_unslash($_POST['bressol_loyalty_opt_in']) : false;
        $marketingOptIn = isset($_POST['bressol_marketing_opt_in']) ? (bool) wp_unslash($_POST['bressol_marketing_opt_in']) : false;

        $order->update_meta_data('_bressol_loyalty_opt_in', $loyaltyOptIn ? 'yes' : 'no');
        $order->update_meta_data('_bressol_marketing_opt_in', $marketingOptIn ? 'yes' : 'no');
        $order->save_meta_data();
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
