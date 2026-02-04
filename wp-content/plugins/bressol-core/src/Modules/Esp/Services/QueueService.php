<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Services;

use Bressol\Modules\Crm\Services\CustomerService;

if (!defined('ABSPATH')) {
    exit;
}

final class QueueService
{
    private AuditLogger $auditLogger;

    public function __construct(?AuditLogger $auditLogger = null)
    {
        $this->auditLogger = $auditLogger ?? new AuditLogger();
    }

    public function enqueue_campaign(int $campaignId): int
    {
        if ($campaignId <= 0) {
            return 0;
        }

        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $jobsTable = $wpdb->prefix . 'bressol_esp_jobs';
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';
        $membersTable = $wpdb->prefix . 'bressol_esp_list_members';

        $tables = [$campaignsTable, $jobsTable, $queueTable, $membersTable];
        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                return 0;
            }
        }

        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, list_id, status FROM {$campaignsTable} WHERE id = %d LIMIT 1",
                $campaignId
            )
        );
        if (!$campaign) {
            return 0;
        }

        $jobId = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$jobsTable} WHERE campaign_id = %d AND status IN ('queued','running','paused') LIMIT 1",
                $campaignId
            )
        );

        if ($jobId <= 0) {
            $now = current_time('mysql');
            $inserted = $wpdb->insert(
                $jobsTable,
                [
                    'campaign_id' => $campaignId,
                    'status' => 'queued',
                    'scheduled_at' => $campaign->scheduled_at ?? null,
                    'started_at' => null,
                    'finished_at' => null,
                    'created_at' => $now,
                ]
            );
            if (!$inserted) {
                return 0;
            }
            $jobId = (int) $wpdb->insert_id;
        }

        $members = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT email, customer_id FROM {$membersTable}
                 WHERE list_id = %d AND status = %s",
                (int) $campaign->list_id,
                'subscribed'
            )
        );

        $now = current_time('mysql');
        foreach ((array) $members as $member) {
            $email = isset($member->email) ? (string) $member->email : '';
            if ($email === '' || !is_email($email)) {
                continue;
            }

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$queueTable}
                     (job_id, list_id, email, customer_id, status, attempts, created_at)
                     VALUES (%d, %d, %s, %d, %s, %d, %s)",
                    $jobId,
                    (int) $campaign->list_id,
                    $email,
                    isset($member->customer_id) ? (int) $member->customer_id : null,
                    'pending',
                    0,
                    $now
                )
            );
        }

        $this->auditLogger->log('job_enqueued', 'campaign', $campaignId, [
            'job_id' => $jobId,
        ]);

        return $jobId;
    }

    public function pause_job(int $jobId): void
    {
        if ($jobId <= 0) {
            return;
        }

        global $wpdb;
        $jobsTable = $wpdb->prefix . 'bressol_esp_jobs';
        $wpdb->update(
            $jobsTable,
            ['status' => 'paused'],
            ['id' => $jobId],
            ['%s'],
            ['%d']
        );
    }

    public function resume_job(int $jobId): void
    {
        if ($jobId <= 0) {
            return;
        }

        global $wpdb;
        $jobsTable = $wpdb->prefix . 'bressol_esp_jobs';
        $wpdb->update(
            $jobsTable,
            ['status' => 'running'],
            ['id' => $jobId],
            ['%s'],
            ['%d']
        );
    }
}
