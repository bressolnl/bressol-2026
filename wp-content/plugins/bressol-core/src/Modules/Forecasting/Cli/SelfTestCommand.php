<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Cli;

use Bressol\Modules\Forecasting\Services\SelfTestService;

if (!defined('ABSPATH')) {
    exit;
}

final class SelfTestCommand
{
    /**
     * Run forecasting selftest.
     *
     * ## OPTIONS
     *
     * --event_id=<id>
     * : Event ID to use for the snapshot.
     *
     * --product_id=<id>
     * : Product ID to use for snapshot lines.
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $eventId = isset($assocArgs['event_id']) ? (int) $assocArgs['event_id'] : 0;
        $productId = isset($assocArgs['product_id']) ? (int) $assocArgs['product_id'] : 0;
        if ($eventId <= 0) {
            \WP_CLI::error('Missing --event_id.');
        }
        if ($productId <= 0) {
            \WP_CLI::error('Missing --product_id.');
        }

        $service = new SelfTestService();
        $steps = $service->run($eventId, $productId);
        foreach ($steps as $step) {
            if (empty($step['ok'])) {
                \WP_CLI::error((string) ($step['message'] ?? 'Selftest failed.'));
            }
        }

        \WP_CLI::success('Forecasting selftest OK.');
    }
}
