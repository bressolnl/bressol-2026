<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Cli;

use Bressol\Modules\CostMargin\Services\MarginRulesService;

if (!defined('ABSPATH')) {
    exit;
}

final class MarginSelfTestCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $channel = isset($assocArgs['channel']) ? (string) $assocArgs['channel'] : 'pos';
        $service = new MarginRulesService();
        $failures = 0;

        $cases = $service->get_selftest_cases($channel);

        foreach ($cases as $case) {
            $result = $service->evaluate_cart($case['items'], $case['context']);
            $status = $result['status'] ?? 'pass';
            $ok = $status === $case['expect'];
            \WP_CLI::log(($ok ? 'PASS' : 'FAIL') . ' ' . $case['name'] . ' status=' . $status);
            if (!$ok) {
                $failures++;
            }
        }

        if ($failures > 0) {
            \WP_CLI::error('Margin selftest failed: ' . $failures);
        }

        \WP_CLI::success('Margin selftest passed.');
    }
}
