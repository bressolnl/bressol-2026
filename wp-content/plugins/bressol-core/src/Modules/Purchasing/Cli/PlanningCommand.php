<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Cli;

use Bressol\Modules\Purchasing\Services\PurchasePlanningService;
use Bressol\Modules\Purchasing\Services\PlanningStore;
use Bressol\Modules\Purchasing\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class PlanningCommand
{
    private Settings $settings;
    private PurchasePlanningService $planningService;
    private PlanningStore $store;

    public function __construct(Settings $settings, PurchasePlanningService $planningService)
    {
        $this->settings = $settings;
        $this->planningService = $planningService;
        $this->store = new PlanningStore();
    }

    /**
     * Run purchase planning reminder.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Do not persist run payload.
     */
    public function run(array $args, array $assocArgs): void
    {
        $dryRun = !empty($assocArgs['dry-run']);
        $payload = $this->planningService->run($dryRun);
        $count = is_array($payload['suggestions'] ?? null) ? count($payload['suggestions']) : 0;
        $result = $dryRun ? 'dry-run' : 'saved';
        \WP_CLI::success('Planning run ' . $result . '. Suggestions=' . $count);
    }

    /**
     * Show planning status.
     */
    public function status(array $args, array $assocArgs): void
    {
        $settings = $this->settings->get();
        $nextShipment = (string) ($settings['purchasing_planning_next_shipment_date_utc'] ?? '');
        $due = $this->planningService->is_due() ? 'true' : 'false';
        $latest = $this->store->get_latest();

        \WP_CLI::log('purchase_planning_enabled=' . (!empty($settings['purchase_planning_enabled']) ? 'true' : 'false'));
        \WP_CLI::log('purchasing_cron_enabled=' . (!empty($settings['purchasing_cron_enabled']) ? 'true' : 'false'));
        \WP_CLI::log('next_shipment_date_utc=' . ($nextShipment !== '' ? $nextShipment : '(not set)'));
        \WP_CLI::log('due=' . $due);
        \WP_CLI::log('latest_run=' . ($latest ? 'yes' : 'no'));
    }
}
