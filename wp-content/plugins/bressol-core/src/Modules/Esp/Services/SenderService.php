<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Services;

use Bressol\Modules\Crm\Services\CustomerService;

if (!defined('ABSPATH')) {
    exit;
}

final class SenderService
{
    private const LOCK_KEY = 'bressol_esp_sender_lock';

    private AuditLogger $auditLogger;
    private Settings $settings;

    public function __construct(?AuditLogger $auditLogger = null, ?Settings $settings = null)
    {
        $this->auditLogger = $auditLogger ?? new AuditLogger();
        $this->settings = $settings ?? new Settings();
    }

    public function run(): void
    {
        if (get_transient(self::LOCK_KEY)) {
            return;
        }
        set_transient(self::LOCK_KEY, '1', 55);

        try {
            $this->process(null);
        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }

    /** @return array{processed:int,sent:int,failed:int,errors:array<int,string>,results:array<int,array{email:string,status:string,error:string}>} */
    public function run_once(int $limit): array
    {
        $limit = max(1, $limit);
        if (get_transient(self::LOCK_KEY)) {
            return [
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
                'errors' => ['queue_locked'],
                'results' => [],
            ];
        }

        $stats = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
            'results' => [],
        ];

        set_transient(self::LOCK_KEY, '1', 55);
        try {
            $this->process($limit, $stats);
        } finally {
            delete_transient(self::LOCK_KEY);
        }

        return $stats;
    }

    /** @param array<string, mixed>|null $stats */
    private function process(?int $limitOverride, ?array &$stats = null): void
    {
        global $wpdb;

        $jobsTable = $wpdb->prefix . 'bressol_esp_jobs';
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';

        $job = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$jobsTable}
                 WHERE status IN ('running','queued')
                   AND (scheduled_at IS NULL OR scheduled_at <= %s)
                 ORDER BY created_at ASC
                 LIMIT 1",
                current_time('mysql')
            )
        );

        if (!$job) {
            return;
        }

        $jobId = (int) $job->id;
        if ((string) $job->status === 'queued') {
            $wpdb->update(
                $jobsTable,
                [
                    'status' => 'running',
                    'started_at' => current_time('mysql'),
                ],
                ['id' => $jobId],
                ['%s', '%s'],
                ['%d']
            );
            $wpdb->update(
                $campaignsTable,
                ['status' => 'sending'],
                ['id' => (int) $job->campaign_id],
                ['%s'],
                ['%d']
            );
        }

        if ((string) $job->status === 'paused') {
            return;
        }

        $settings = $this->settings->get_settings();
        $dailyCap = (int) $settings['daily_cap'];
        $batchSize = (int) $settings['batch_size'];

        $todayStart = date('Y-m-d 00:00:00', current_time('timestamp'));
        $sentToday = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$queueTable} WHERE status = 'sent' AND sent_at >= %s",
                $todayStart
            )
        );
        if ($dailyCap > 0 && $sentToday >= $dailyCap) {
            return;
        }

        $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - 600);
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$queueTable}
                 SET locked_at = NULL
                 WHERE job_id = %d AND status = 'pending' AND locked_at IS NOT NULL AND locked_at < %s",
                $jobId,
                $cutoff
            )
        );

        $remaining = $dailyCap > 0 ? max(0, $dailyCap - $sentToday) : $batchSize;
        if ($remaining <= 0) {
            return;
        }
        $limit = $limitOverride !== null ? min($limitOverride, $remaining) : min($batchSize, $remaining);

        $candidateIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$queueTable}
                 WHERE job_id = %d AND status = 'pending' AND locked_at IS NULL
                 ORDER BY id ASC
                 LIMIT %d",
                $jobId,
                $limit
            )
        );
        if (!$candidateIds) {
            $items = [];
        } else {
            $placeholders = implode(',', array_fill(0, count($candidateIds), '%d'));
            $lockTime = current_time('mysql');
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$queueTable}
                     SET locked_at = %s
                     WHERE job_id = %d AND status = 'pending' AND locked_at IS NULL AND id IN ({$placeholders})",
                    array_merge([$lockTime, $jobId], $candidateIds)
                )
            );
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$queueTable}
                     WHERE job_id = %d AND status = 'pending' AND locked_at = %s
                     ORDER BY id ASC",
                    $jobId,
                    $lockTime
                )
            );
        }

        if (!$items) {
            $pending = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$queueTable} WHERE job_id = %d AND status = 'pending'",
                    $jobId
                )
            );
            if ($pending === 0) {
                $wpdb->update(
                    $jobsTable,
                    [
                        'status' => 'done',
                        'finished_at' => current_time('mysql'),
                    ],
                    ['id' => $jobId],
                    ['%s', '%s'],
                    ['%d']
                );
                $wpdb->update(
                    $campaignsTable,
                    ['status' => 'sent'],
                    ['id' => (int) $job->campaign_id],
                    ['%s'],
                    ['%d']
                );
            }
            return;
        }

        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$campaignsTable} WHERE id = %d LIMIT 1",
                (int) $job->campaign_id
            )
        );
        if (!$campaign) {
            return;
        }

        foreach ($items as $item) {
            $email = isset($item->email) ? (string) $item->email : '';
            $error = '';
            $status = '';
            if ($email === '' || !is_email($email)) {
                $this->mark_skipped((int) $item->id, 'invalid_email');
                $error = 'invalid_email';
                $status = 'skipped';
                if ($stats !== null) {
                    $stats['processed']++;
                    $stats['failed']++;
                    $stats['errors'][] = $error;
                    $stats['results'][] = ['email' => $email, 'status' => $status, 'error' => $error];
                }
                continue;
            }

            $consent = $this->check_marketing_consent($email);
            if (!$consent['allowed']) {
                $this->mark_skipped((int) $item->id, 'consent_missing');
                $error = 'consent_missing';
                $status = 'skipped';
                if ($stats !== null) {
                    $stats['processed']++;
                    $stats['failed']++;
                    $stats['errors'][] = $error;
                    $stats['results'][] = ['email' => $email, 'status' => $status, 'error' => $error];
                }
                continue;
            }
            if ($consent['customer_id'] > 0) {
                $wpdb->update(
                    $queueTable,
                    ['customer_id' => (int) $consent['customer_id']],
                    ['id' => (int) $item->id],
                    ['%d'],
                    ['%d']
                );
            }

            if ($dailyCap > 0 && $sentToday >= $dailyCap) {
                $this->release_lock((int) $item->id);
                foreach ($items as $remaining) {
                    if ((int) $remaining->id === (int) $item->id) {
                        continue;
                    }
                    $this->release_lock((int) $remaining->id);
                }
                break;
            }

            $subject = (string) $campaign->subject;
            $body = (string) $campaign->html_body;
            $body .= $this->build_unsub_footer($email, (int) $campaign->list_id);

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            Settings::set_smtp_allowed(true);
            try {
                $sent = wp_mail($email, $subject, $body, $headers);
            } finally {
                Settings::set_smtp_allowed(false);
            }

            if ($sent) {
                $sentToday++;
                $wpdb->update(
                    $queueTable,
                    [
                        'status' => 'sent',
                        'sent_at' => current_time('mysql'),
                        'locked_at' => null,
                    ],
                    ['id' => (int) $item->id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
                $this->auditLogger->log('send_success', 'campaign', (int) $campaign->id, [
                    'email' => $email,
                ]);
                if ($stats !== null) {
                    $stats['processed']++;
                    $stats['sent']++;
                    $stats['results'][] = ['email' => $email, 'status' => 'sent', 'error' => ''];
                }
            } else {
                $status = $this->mark_failed((int) $item->id, 'wp_mail_failed');
                $error = 'wp_mail_failed';
                if ($stats !== null) {
                    $stats['processed']++;
                    $stats['failed']++;
                    $stats['errors'][] = $error;
                    $stats['results'][] = ['email' => $email, 'status' => $status, 'error' => $error];
                }
            }
        }
    }

    private function check_marketing_consent(string $email): array
    {
        if (class_exists(CustomerService::class)) {
            global $wpdb;
            $table = $wpdb->prefix . 'bressol_crm_customers';
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists === $table) {
                $service = new CustomerService();
                $state = $service->get_effective_marketing_state($email);
                $allowed = (bool) ($state['effective_flags']['can_receive_marketing'] ?? false);
                $customerId = (int) ($state['customer_id'] ?? 0);
                return ['allowed' => $allowed, 'customer_id' => $customerId];
            }
        }

        $this->auditLogger->log('crm_missing_consents_check', 'consent', null, [
            'email' => $email,
        ]);

        return ['allowed' => true, 'customer_id' => 0];
    }

    private function mark_skipped(int $itemId, string $reason): void
    {
        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';
        $wpdb->update(
            $queueTable,
            [
                'status' => 'skipped',
                'last_error' => $reason,
                'locked_at' => null,
            ],
            ['id' => $itemId],
            ['%s', '%s', '%s'],
            ['%d']
        );
        $this->auditLogger->log('skipped_reason', 'queue_item', $itemId, [
            'reason' => $reason,
        ]);
    }

    private function mark_failed(int $itemId, string $error): string
    {
        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';
        $attempts = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attempts FROM {$queueTable} WHERE id = %d",
                $itemId
            )
        );
        $attempts++;
        $status = $attempts >= 3 ? 'failed' : 'pending';
        $wpdb->update(
            $queueTable,
            [
                'status' => $status,
                'attempts' => $attempts,
                'last_error' => $error,
                'locked_at' => null,
            ],
            ['id' => $itemId],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );
        $this->auditLogger->log('send_failed', 'queue_item', $itemId, [
            'error' => $error,
            'attempts' => $attempts,
        ]);
        return $status;
    }

    private function release_lock(int $itemId): void
    {
        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';
        $wpdb->update(
            $queueTable,
            ['locked_at' => null],
            ['id' => $itemId],
            ['%s'],
            ['%d']
        );
    }

    private function build_unsub_footer(string $email, int $listId): string
    {
        $service = new UnsubscribeService();
        $token = $service->build_token($email, $listId);
        $url = add_query_arg(
            [
                'action' => 'bressol_esp_unsub',
                'token' => $token,
            ],
            admin_url('admin-post.php')
        );

        return '<hr /><p><a href="' . esc_url($url) . '">Darse de baja</a></p>';
    }
}
