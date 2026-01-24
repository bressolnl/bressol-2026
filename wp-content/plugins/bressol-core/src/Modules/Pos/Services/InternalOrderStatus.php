<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class InternalOrderStatus
{
    public const STATUS = 'wc-bressol-internal';
    public const LABEL = 'Bressol – Intern';

    public function register(): void
    {
        add_action('init', [$this, 'register_status']);
        add_filter('wc_order_statuses', [$this, 'add_status_to_list']);
        $this->register_email_guards();
    }

    public function register_status(): void
    {
        register_post_status(self::STATUS, [
            'label' => self::LABEL,
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Bressol – Intern <span class="count">(%s)</span>',
                'Bressol – Intern <span class="count">(%s)</span>'
            ),
        ]);
    }

    /** @param array<string, string> $statuses */
    public function add_status_to_list(array $statuses): array
    {
        $statuses[self::STATUS] = self::LABEL;
        return $statuses;
    }

    private function register_email_guards(): void
    {
        $filters = [
            'woocommerce_email_enabled_new_order',
            'woocommerce_email_enabled_cancelled_order',
            'woocommerce_email_enabled_failed_order',
            'woocommerce_email_enabled_customer_on_hold_order',
            'woocommerce_email_enabled_customer_processing_order',
            'woocommerce_email_enabled_customer_completed_order',
            'woocommerce_email_enabled_customer_refunded_order',
            'woocommerce_email_enabled_customer_invoice',
            'woocommerce_email_enabled_customer_note',
        ];

        foreach ($filters as $filter) {
            add_filter($filter, [$this, 'maybe_disable_email'], 10, 2);
        }
    }

    /** @param \WC_Order|\WC_Order_Refund|null $order */
    public function maybe_disable_email(bool $enabled, $order): bool
    {
        if (!$order instanceof \WC_Order) {
            return $enabled;
        }

        $meta = (string) $order->get_meta('_bressol_internal_order');
        if ($meta === '1') {
            return false;
        }

        return (string) $order->get_status() === 'bressol-internal' ? false : $enabled;
    }
}
