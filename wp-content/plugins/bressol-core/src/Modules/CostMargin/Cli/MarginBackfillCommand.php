<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Cli;

use Bressol\Modules\CostMargin\Services\MarginAuditService;

if (!defined('ABSPATH')) {
    exit;
}

final class MarginBackfillCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $after = isset($assocArgs['after']) ? (string) $assocArgs['after'] : '';
        if ($after === '') {
            \WP_CLI::error('Missing --after (YYYY-MM-DD).');
        }

        $channelFilter = isset($assocArgs['channel']) ? (string) $assocArgs['channel'] : '';
        if ($channelFilter !== '' && $channelFilter !== 'pos' && $channelFilter !== 'online') {
            \WP_CLI::error('Invalid --channel. Use pos|online.');
        }

        if (!function_exists('wc_get_order')) {
            \WP_CLI::error('WooCommerce not available.');
        }

        $dryRun = !empty($assocArgs['dry-run']) || !empty($assocArgs['dry_run']);
        $service = new MarginAuditService();
        $processed = 0;
        $skipped = 0;
        $examples = [];

        $paged = 1;
        do {
            $query = new \WP_Query([
                'post_type' => 'shop_order',
                'post_status' => 'wc-completed',
                'posts_per_page' => 200,
                'paged' => $paged,
                'fields' => 'ids',
                'date_query' => [
                    [
                        'after' => $after,
                        'inclusive' => true,
                    ],
                ],
            ]);

            if (!$query->have_posts()) {
                break;
            }

            foreach ($query->posts as $orderId) {
                $orderId = (int) $orderId;
                if ($orderId <= 0) {
                    continue;
                }

                $order = wc_get_order($orderId);
                if (!$order instanceof \WC_Order) {
                    continue;
                }

                $checkedAt = (string) $order->get_meta('_bressol_margin_checked_at');
                if ($checkedAt !== '') {
                    $skipped++;
                    continue;
                }

                $channel = ((string) $order->get_meta('_bressol_pos_channel') === 'pos') ? 'pos' : 'online';
                if ($channelFilter !== '' && $channel !== $channelFilter) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    if (count($examples) < 5) {
                        $examples[] = $orderId;
                    }
                    $processed++;
                    continue;
                }

                $marketCost = $channel === 'pos' ? (int) $order->get_meta('_bressol_pos_market_cost_cents') : 0;
                $service->evaluate_and_persist_order($order, [
                    'channel' => $channel,
                    'market_cost_cents' => $marketCost,
                    'price_source' => 'woo_fallback',
                ]);
                $processed++;
            }

            $paged++;
        } while ($paged <= $query->max_num_pages);

        if ($dryRun) {
            \WP_CLI::log('dry_run=1 candidates=' . $processed . ' skipped=' . $skipped);
            if ($examples !== []) {
                \WP_CLI::log('examples=' . implode(',', $examples));
            }
            return;
        }

        \WP_CLI::success('Backfill complete. processed=' . $processed . ' skipped=' . $skipped);
    }
}
