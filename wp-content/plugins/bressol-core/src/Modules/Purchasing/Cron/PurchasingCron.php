<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Cron;

use Bressol\Modules\Purchasing\Services\AuditLogger;
use Bressol\Modules\Purchasing\Services\PurchasePlanningService;
use Bressol\Modules\Purchasing\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class PurchasingCron
{
    private const HOOK = 'bressol_purchasing_purchase_planning_reminder';

    private Settings $settings;
    private AuditLogger $auditLogger;
    private PurchasePlanningService $planningService;

    public function __construct(Settings $settings, AuditLogger $auditLogger, PurchasePlanningService $planningService)
    {
        $this->settings = $settings;
        $this->auditLogger = $auditLogger;
        $this->planningService = $planningService;
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'run_reminder']);

        if ($this->settings->is_purchasing_cron_enabled()) {
            $this->maybe_schedule();
        } else {
            $this->maybe_clear();
        }
    }

    public function run_reminder(): void
    {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }

        if (!$this->settings->is_purchasing_cron_enabled() || !$this->settings->is_purchase_planning_enabled()) {
            return;
        }

        if (!$this->planningService->is_due()) {
            return;
        }

        try {
            $this->planningService->run(false);
        } catch (\Throwable $exception) {
            $this->auditLogger->log('planning_run_error', [
                'result' => 'exception',
            ]);
        }
    }

    private function maybe_schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 300, 'daily', self::HOOK);
        }
    }

    private function maybe_clear(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }
}
