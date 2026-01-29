<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Services;

use Bressol\Modules\B2B\Repositories\LeadEventsRepository;
use Bressol\Modules\B2B\Repositories\LeadRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ReminderService
{
    public const CRON_HOOK = 'bressol_b2b_daily';
    private const LOOKBACK_DAYS = 7;

    private LeadRepository $leads;
    private LeadEventsRepository $events;

    public function __construct(?LeadRepository $leads = null, ?LeadEventsRepository $events = null)
    {
        $this->leads = $leads ?? new LeadRepository();
        $this->events = $events ?? new LeadEventsRepository();
    }

    public function schedule(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }
        wp_schedule_event(time() + 300, 'daily', self::CRON_HOOK);
    }

    public function run(): void
    {
        $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - (self::LOOKBACK_DAYS * 86400));
        $leads = $this->leads->find_for_reminder($cutoff, 50);
        if ($leads === []) {
            return;
        }

        $esp = new EspService();
        foreach ($leads as $lead) {
            $leadId = (int) ($lead['id'] ?? 0);
            $token = (string) ($lead['consent_token'] ?? '');
            if ($leadId <= 0 || $token === '') {
                continue;
            }
            $queued = $esp->enqueue_b2b_reminder_email($lead, $token);
            if ($queued) {
                $now = current_time('mysql');
                $this->leads->update($leadId, [
                    'reminder_sent_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->events->insert_event($leadId, 'reminder_email_queued', []);
            }
        }
    }
}
