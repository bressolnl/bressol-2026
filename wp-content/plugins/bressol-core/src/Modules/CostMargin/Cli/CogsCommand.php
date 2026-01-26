<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Cli;

use Bressol\Modules\CostMargin\Services\OrderCogsFinalizer;

if (!defined('ABSPATH')) {
    exit;
}

final class CogsCommand
{
    public function finalize(array $args, array $assocArgs): void
    {
        $orderId = isset($assocArgs['order_id']) ? (int) $assocArgs['order_id'] : 0;
        if ($orderId <= 0) {
            \WP_CLI::error('Missing --order_id.');
        }

        $service = new OrderCogsFinalizer();
        $service->finalize_order($orderId);
        \WP_CLI::success('COGS finalize attempted for order ' . $orderId);
    }

    public function show(array $args, array $assocArgs): void
    {
        $orderId = isset($assocArgs['order_id']) ? (int) $assocArgs['order_id'] : 0;
        if ($orderId <= 0) {
            \WP_CLI::error('Missing --order_id.');
        }

        if (!function_exists('wc_get_order')) {
            \WP_CLI::error('WooCommerce not available.');
        }

        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            \WP_CLI::error('Order not found.');
        }

        $status = (string) $order->get_meta('_bressol_cogs_status');
        $real = (int) $order->get_meta('_bressol_cogs_real_cents');
        $note = (string) $order->get_meta('_bressol_cogs_note');

        \WP_CLI::log('status=' . $status . ' real_cogs_cents=' . $real . ' note=' . $note);

        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $raw = $item->get_meta('_bressol_lot_allocations', true);
            $allocations = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($allocations)) {
                continue;
            }
            \WP_CLI::log('item=' . (int) $item->get_id() . ' allocations=' . wp_json_encode($allocations));
        }
    }
}
