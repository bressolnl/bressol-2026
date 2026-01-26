<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Cli;

use Bressol\Modules\CostMargin\Services\CostMarginService;

if (!defined('ABSPATH')) {
    exit;
}

final class CostCommand
{
    public function unit(array $args, array $assocArgs): void
    {
        $productId = isset($assocArgs['product_id']) ? (int) $assocArgs['product_id'] : 0;
        if ($productId <= 0) {
            \WP_CLI::error('Missing --product_id.');
        }

        $service = new CostMarginService();
        $unit = $service->get_unit_cost_cents($productId, 'NL', current_time('Y-m-d'));
        if ($unit === null) {
            \WP_CLI::warning('No unit cost available.');
            return;
        }

        \WP_CLI::success('Unit cost cents: ' . $unit);
    }

    public function order(array $args, array $assocArgs): void
    {
        $orderId = isset($assocArgs['order_id']) ? (int) $assocArgs['order_id'] : 0;
        if ($orderId <= 0) {
            \WP_CLI::error('Missing --order_id.');
        }

        $service = new CostMarginService();
        $payload = $service->estimate_order_cogs_cents($orderId);
        $cogs = is_array($payload) ? (int) ($payload['cogs_cents'] ?? 0) : (int) $payload;
        $missing = is_array($payload) && !empty($payload['missing_cost']) ? 'yes' : 'no';
        \WP_CLI::success('Order COGS cents: ' . $cogs . ' | missing_cost=' . $missing);
    }
}
