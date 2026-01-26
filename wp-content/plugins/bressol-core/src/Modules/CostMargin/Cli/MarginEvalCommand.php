<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Cli;

use Bressol\Modules\CostMargin\Services\MarginRulesService;

if (!defined('ABSPATH')) {
    exit;
}

final class MarginEvalCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $channel = isset($assocArgs['channel']) ? (string) $assocArgs['channel'] : 'pos';
        $itemsRaw = isset($assocArgs['items']) ? (string) $assocArgs['items'] : '[]';
        $marketCost = isset($assocArgs['market_cost_cents']) ? (int) $assocArgs['market_cost_cents'] : 0;
        $isClearance = !empty($assocArgs['is_clearance']);

        $decoded = json_decode($itemsRaw, true);
        if (!is_array($decoded)) {
            \WP_CLI::error('Invalid --items JSON.');
        }

        $service = new MarginRulesService();
        $result = $service->evaluate_cart($decoded, [
            'channel' => $channel,
            'market_cost_cents' => $marketCost,
            'is_clearance' => $isClearance,
        ]);

        \WP_CLI::log('status=' . $result['status']);
        \WP_CLI::log('computed=' . wp_json_encode($result['computed']));
        foreach ($result['violations'] as $violation) {
            \WP_CLI::log(wp_json_encode($violation));
        }
    }
}
