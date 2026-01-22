<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Crm\Admin\AdminPages;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Crm\Services\PointsService;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

final class CrmModule implements ModuleInterface
{
    public const CRON_HOOK = 'bressol_crm_expire_points';

    public function register(): void
    {
        (new Installer())->maybe_upgrade();

        if (is_admin()) {
            (new AdminPages())->register();
        }

        add_action(self::CRON_HOOK, [$this, 'expirePoints']);
        self::schedule_cron();

        if (!class_exists('\\WooCommerce')) {
            return;
        }

        add_action('woocommerce_order_status_completed', [$this, 'handleOrderStatusChange'], 10, 1);
        add_action('woocommerce_order_status_processing', [$this, 'handleOrderStatusChange'], 10, 1);
        add_action('woocommerce_order_refunded', [$this, 'handleOrderRefund'], 10, 2);
    }

    public function handleOrderStatusChange(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        $customer_service = new CustomerService();
        $customer_id = $customer_service->get_or_create_from_order($order);
        $customer = $customer_service->get_by_id($customer_id);

        if (!$customer) {
            return;
        }

        $customer_service->update_cached_metrics($customer_id, $order);

        $points_service = new PointsService();
        $points_service->award_points_for_order($customer_id, $customer, $order);
    }

    public function handleOrderRefund(int $order_id, int $refund_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        $customer_service = new CustomerService();
        $customer_id = $customer_service->get_or_create_from_order($order);
        $customer = $customer_service->get_by_id($customer_id);

        if (!$customer) {
            return;
        }

        $points_service = new PointsService();
        $points_service->refund_points_for_order($customer_id, $customer, $order_id, $refund_id);
        $customer_service->update_cached_metrics($customer_id, $order);
    }

    public function expirePoints(): void
    {
        (new PointsService())->expire_points();
    }

    public static function schedule_cron(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
    }

    public static function clear_cron(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
