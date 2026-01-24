<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\SalesAnalytics\Admin\AdminPages;
use Bressol\Modules\SalesAnalytics\Cli\SeedDemoCommand;
use Bressol\Modules\SalesAnalytics\Cli\SelfTestCommand;
use Bressol\Modules\SalesAnalytics\Repositories\OrderQuery;
use Bressol\Modules\SalesAnalytics\Services\AuditLogger;
use Bressol\Modules\SalesAnalytics\Services\Capabilities;
use Bressol\Modules\SalesAnalytics\Services\CacheService;
use Bressol\Modules\SalesAnalytics\Services\ExportService;
use Bressol\Modules\SalesAnalytics\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class SalesAnalyticsModule implements ModuleInterface
{
    private const EXPORT_RATE_LIMIT_TTL = 120;
    private const EXPORT_ORDERS_MAX_DAYS = 180;
    private const EXPORT_ORDERS_MAX_COUNT = 20000;
    private const EXPORT_DAILY_MAX_DAYS = 60;
    private const EXPORT_DAILY_MAX_COUNT = 5000;
    private const EXPORT_DISCREPANCIES_MAX_COUNT = 2000;

    private Capabilities $capabilities;
    private Settings $settings;
    private ExportService $exportService;
    private CacheService $cacheService;
    private AuditLogger $auditLogger;

    public function register(): void
    {
        $this->capabilities = new Capabilities();
        $this->settings = new Settings();
        $this->cacheService = new CacheService();
        $this->exportService = new ExportService($this->settings, $this->capabilities, $this->cacheService);
        $this->auditLogger = new AuditLogger();

        add_action('admin_init', [Capabilities::class, 'ensure_caps_registered']);

        if (is_admin()) {
            $adminPages = new AdminPages($this->settings, $this->capabilities, $this->cacheService);
            add_action('admin_menu', [$adminPages, 'registerMenus']);
            add_action('admin_enqueue_scripts', [$adminPages, 'enqueueAssets']);
        }

        add_action('admin_post_bressol_sales_export_orders', [$this, 'handleExportOrders']);
        add_action('admin_post_bressol_sales_export_daily', [$this, 'handleExportDaily']);
        add_action('admin_post_bressol_sales_export_discrepancies', [$this, 'handleExportDiscrepancies']);
        add_action('woocommerce_order_status_changed', [$this, 'handleOrderStatusChanged'], 10, 4);
        add_action('woocommerce_order_refunded', [$this, 'handleOrderRefunded'], 10, 2);

        if (defined('WP_CLI') && WP_CLI && class_exists('WooCommerce') && class_exists('\\WP_CLI')) {
            \WP_CLI::add_command('bressol sales-analytics seed-demo', new SeedDemoCommand());
            \WP_CLI::add_command('bressol sales-analytics selftest', new SelfTestCommand());
        }
    }

    public function handleExportOrders(): void
    {
        if (!$this->capabilities->current_user_can_view()) {
            $this->log_export_blocked('orders', [], false, 'permission');
            $this->redirect_export_error('forbidden');
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bressol_sales_export_orders_nonce')) {
            $filters = $this->sanitize_filters_from_request($_POST);
            $includePii = !empty($_POST['include_pii']);
            $this->log_export_blocked('orders', $filters, $includePii, 'nonce');
            $this->redirect_export_error('invalid_nonce');
        }

        $includePii = !empty($_POST['include_pii']);
        if ($includePii && (!$this->settings->is_pii_export_enabled() || !$this->capabilities->current_user_can_export_pii())) {
            $filters = $this->sanitize_filters_from_request($_POST);
            $this->log_export_blocked('orders', $filters, $includePii, 'permission');
            $this->redirect_export_error('pii_forbidden');
        }

        $filters = $this->sanitize_filters_from_request($_POST);
        $this->log_export_attempted('orders', $filters, $includePii);

        if ($this->is_rate_limited('orders')) {
            $this->log_export_blocked('orders', $filters, $includePii, 'rate_limit');
            $this->redirect_export_error('rate_limited');
        }

        if ($this->is_orders_export_too_large($filters)) {
            $this->log_export_blocked('orders', $filters, $includePii, 'range_too_large');
            $this->redirect_export_error('range_too_large');
        }

        try {
            $this->exportService->stream_orders_csv($filters, $includePii);
            exit;
        } catch (\Throwable $exception) {
            $this->log_export_exception('orders', $filters, $includePii, $exception);
            wp_die('No se pudo completar la exportación.');
        }
    }

    public function handleExportDaily(): void
    {
        if (!$this->capabilities->current_user_can_view()) {
            $this->log_export_blocked('daily', [], false, 'permission');
            $this->redirect_export_error('forbidden');
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bressol_sales_export_daily_nonce')) {
            $filters = $this->sanitize_filters_from_request($_POST);
            $this->log_export_blocked('daily', $filters, false, 'nonce');
            $this->redirect_export_error('invalid_nonce');
        }

        $filters = $this->sanitize_filters_from_request($_POST);
        $this->log_export_attempted('daily', $filters, false);

        if ($this->is_rate_limited('daily')) {
            $this->log_export_blocked('daily', $filters, false, 'rate_limit');
            $this->redirect_export_error('rate_limited');
        }

        if ($this->is_daily_export_too_large($filters)) {
            $this->log_export_blocked('daily', $filters, false, 'range_too_large');
            $this->redirect_export_error('range_too_large');
        }

        try {
            $this->exportService->stream_daily_market_csv($filters);
            exit;
        } catch (\Throwable $exception) {
            $this->log_export_exception('daily', $filters, false, $exception);
            wp_die('No se pudo completar la exportación.');
        }
    }

    public function handleExportDiscrepancies(): void
    {
        if (!$this->capabilities->current_user_can_view()) {
            $this->log_export_blocked('discrepancies', [], false, 'permission');
            $this->redirect_export_error('forbidden');
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bressol_sales_export_discrepancies_nonce')) {
            $filters = $this->sanitize_filters_from_request($_POST);
            $this->log_export_blocked('discrepancies', $filters, false, 'nonce');
            $this->redirect_export_error('invalid_nonce');
        }

        $filters = $this->sanitize_filters_from_request($_POST);
        $this->log_export_attempted('discrepancies', $filters, false);

        if ($this->is_rate_limited('discrepancies')) {
            $this->log_export_blocked('discrepancies', $filters, false, 'rate_limit');
            $this->redirect_export_error('rate_limited');
        }

        if ($this->estimate_orders_count($filters, self::EXPORT_DISCREPANCIES_MAX_COUNT + 1) > self::EXPORT_DISCREPANCIES_MAX_COUNT) {
            $this->log_export_blocked('discrepancies', $filters, false, 'range_too_large');
            $this->redirect_export_error('range_too_large');
        }

        try {
            $this->exportService->stream_discrepancies_csv($filters);
            exit;
        } catch (\Throwable $exception) {
            $this->log_export_exception('discrepancies', $filters, false, $exception);
            wp_die('No se pudo completar la exportación.');
        }
    }

    /** @param array<string, mixed> $input */
    private function sanitize_filters_from_request(array $input): array
    {
        $from = isset($input['date_from']) ? sanitize_text_field(wp_unslash($input['date_from'])) : '';
        $to = isset($input['date_to']) ? sanitize_text_field(wp_unslash($input['date_to'])) : '';
        $channel = isset($input['channel']) ? sanitize_text_field(wp_unslash($input['channel'])) : 'all';
        $marketId = isset($input['market_id']) ? sanitize_text_field(wp_unslash($input['market_id'])) : '';

        return [
            'date_from' => $this->sanitize_date($from),
            'date_to' => $this->sanitize_date($to),
            'channel' => $this->sanitize_channel($channel),
            'market_id' => $marketId,
        ];
    }

    private function sanitize_date(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    private function sanitize_channel(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || $value === 'all') {
            return 'all';
        }

        return in_array($value, ['web', 'pos'], true) ? $value : 'all';
    }

    private function redirect_export_error(string $code): void
    {
        $url = add_query_arg([
            'page' => 'bressol-sales-analytics-exports',
            'sales_error' => $code,
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private function is_rate_limited(string $type): bool
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return false;
        }

        $key = 'bressol_sa_export_rl_' . $userId;
        if (get_transient($key)) {
            return true;
        }

        set_transient($key, [
            'type' => $type,
            'at' => time(),
        ], self::EXPORT_RATE_LIMIT_TTL);

        return false;
    }

    private function is_orders_export_too_large(array $filters): bool
    {
        if ($this->is_date_range_over_limit($filters, self::EXPORT_ORDERS_MAX_DAYS)) {
            return true;
        }

        return $this->estimate_orders_count($filters, self::EXPORT_ORDERS_MAX_COUNT + 1) > self::EXPORT_ORDERS_MAX_COUNT;
    }

    private function is_daily_export_too_large(array $filters): bool
    {
        if ($this->is_date_range_over_limit($filters, self::EXPORT_DAILY_MAX_DAYS)) {
            return true;
        }

        return $this->estimate_orders_count($filters, self::EXPORT_DAILY_MAX_COUNT + 1) > self::EXPORT_DAILY_MAX_COUNT;
    }

    private function is_date_range_over_limit(array $filters, int $limitDays): bool
    {
        $from = isset($filters['date_from']) ? (string) $filters['date_from'] : '';
        $to = isset($filters['date_to']) ? (string) $filters['date_to'] : '';
        if ($from === '' || $to === '') {
            return false;
        }

        $fromDate = \DateTimeImmutable::createFromFormat('Y-m-d', $from);
        $toDate = \DateTimeImmutable::createFromFormat('Y-m-d', $to);
        if (!$fromDate || !$toDate) {
            return false;
        }

        $days = (int) $fromDate->diff($toDate)->days;
        return $days > $limitDays;
    }

    private function estimate_orders_count(array $filters, int $maxCount): int
    {
        $count = 0;
        $page = 1;
        $limit = 200;
        $query = new OrderQuery();

        do {
            $ids = $query->find_order_ids($filters, $limit, $page);
            if ($ids === []) {
                break;
            }

            $count += count($ids);
            if ($count >= $maxCount) {
                return $count;
            }

            $page++;
        } while (true);

        return $count;
    }

    private function log_export_attempted(string $type, array $filters, bool $includePii): void
    {
        if ($type === 'daily') {
            $action = 'sales_export_daily_attempted';
        } elseif ($type === 'discrepancies') {
            $action = 'sales_export_discrepancies_attempted';
        } else {
            $action = 'sales_export_orders_attempted';
        }

        $this->auditLogger->log($action, [
            'export_type' => $type,
            'filters' => $filters,
            'include_pii' => $includePii,
            'result' => 'attempted',
            'request_uri' => $this->get_request_uri(),
        ]);
    }

    private function log_export_blocked(string $type, array $filters, bool $includePii, string $reason): void
    {
        $this->auditLogger->log('sales_export_blocked_' . $reason, [
            'export_type' => $type,
            'filters' => $filters,
            'include_pii' => $includePii,
            'result' => 'blocked',
            'reason' => $reason,
            'request_uri' => $this->get_request_uri(),
        ]);
    }

    private function log_export_exception(string $type, array $filters, bool $includePii, \Throwable $exception): void
    {
        $this->auditLogger->log('sales_export_failed_exception', [
            'export_type' => $type,
            'filters' => $filters,
            'include_pii' => $includePii,
            'result' => 'error',
            'reason' => 'exception',
            'exception_class' => get_class($exception),
            'message_truncated' => $exception->getMessage(),
            'request_uri' => $this->get_request_uri(),
        ]);
    }

    private function get_request_uri(): string
    {
        return isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    }

    public function handleOrderStatusChanged(int $orderId, string $oldStatus, string $newStatus, \WC_Order $order): void
    {
        if ($newStatus === 'completed' || $oldStatus === 'completed') {
            $this->cacheService->bump_version();
        }
    }

    public function handleOrderRefunded(int $orderId, int $refundId): void
    {
        $this->cacheService->bump_version();
    }
}
