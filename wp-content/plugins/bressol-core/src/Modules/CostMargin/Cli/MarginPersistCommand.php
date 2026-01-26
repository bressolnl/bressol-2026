<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Cli;

use Bressol\Modules\CostMargin\Services\MarginAuditService;

if (!defined('ABSPATH')) {
    exit;
}

final class MarginPersistCommand
{
    public function __invoke(array $args, array $assocArgs): void
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

        $channel = ((string) $order->get_meta('_bressol_pos_channel') === 'pos') ? 'pos' : 'online';
        $marketCost = $channel === 'pos' ? (int) $order->get_meta('_bressol_pos_market_cost_cents') : 0;

        (new MarginAuditService())->evaluate_and_persist_order($order, [
            'channel' => $channel,
            'market_cost_cents' => $marketCost,
            'price_source' => 'woo_fallback',
        ]);

        \WP_CLI::success('Margin audit persisted for order ' . $orderId);
    }
}
