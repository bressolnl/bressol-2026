<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp;

use Bressol\Core\ModuleInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class EspModule implements ModuleInterface
{
    private const MAX_QUEUE_ATTEMPTS = 3;
    private const RETRY_MINUTES = 15;
    private const CRON_HOOK_QUEUE = 'bressol_esp_process_send_queue';
    private const CRON_HOOK_CAMPAIGNS = 'bressol_esp_automate_campaigns';
    private const CRON_INTERVAL = 'bressol_esp_minute';

    public function register(): void
    {
        add_action('init', [$this, 'handleOpenTracking']);
        add_action('init', [$this, 'handleClickTracking']);
        add_action('init', [$this, 'handleUnsubscribe']);
        add_filter('cron_schedules', [self::class, 'registerCronSchedules']);
        add_action('phpmailer_init', [$this, 'configureSmtp']);
        add_action(self::CRON_HOOK_QUEUE, [$this, 'processSendQueue']);
        add_action(self::CRON_HOOK_CAMPAIGNS, [$this, 'automateCampaigns']);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
        }
    }

    public static function registerCronSchedules(array $schedules): array
    {
        $schedules[self::CRON_INTERVAL] = [
            'interval' => 60,
            'display' => 'Cada minuto (ESP)',
        ];

        return $schedules;
    }

    public static function scheduleCron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK_QUEUE)) {
            wp_schedule_event(time() + 60, self::CRON_INTERVAL, self::CRON_HOOK_QUEUE);
        }

        if (!wp_next_scheduled(self::CRON_HOOK_CAMPAIGNS)) {
            wp_schedule_event(time() + 60, self::CRON_INTERVAL, self::CRON_HOOK_CAMPAIGNS);
        }
    }

    public static function clearCron(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK_QUEUE);
        wp_clear_scheduled_hook(self::CRON_HOOK_CAMPAIGNS);
    }

    public function configureSmtp(\PHPMailer\PHPMailer\PHPMailer $phpmailer): void
    {
        $settings = get_option('bressol_esp_smtp', []);
        if (empty($settings['host']) || empty($settings['port']) || empty($settings['from_email'])) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = (string) $settings['host'];
        $phpmailer->Port = (int) $settings['port'];
        $phpmailer->SMTPAuth = !empty($settings['username']);
        $phpmailer->Username = (string) ($settings['username'] ?? '');
        $phpmailer->Password = (string) ($settings['password'] ?? '');
        $phpmailer->SMTPSecure = $settings['encryption'] !== 'none' ? (string) $settings['encryption'] : '';
        $phpmailer->setFrom(
            (string) $settings['from_email'],
            (string) ($settings['from_name'] ?? '')
        );
    }

    public function handleOpenTracking(): void
    {
        if (!isset($_GET['bressol_esp_open'], $_GET['tracking_key'])) {
            return;
        }

        $trackingKey = sanitize_text_field(wp_unslash($_GET['tracking_key']));
        if ($trackingKey === '') {
            $this->outputTrackingPixel();
            return;
        }

        global $wpdb;
        $manualEmailsTable = $wpdb->prefix . 'bressol_esp_manual_emails';
        $eventsTable = $wpdb->prefix . 'bressol_esp_events';

        $manualEmail = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, recipient_email FROM {$manualEmailsTable} WHERE tracking_key = %s LIMIT 1",
                $trackingKey
            )
        );

        if ($manualEmail) {
            $wpdb->insert(
                $eventsTable,
                [
                    'campaign_id' => null,
                    'manual_email_id' => $manualEmail->id,
                    'user_id' => null,
                    'recipient_email' => $manualEmail->recipient_email,
                    'event_type' => 'open',
                    'url' => null,
                    'created_at' => current_time('mysql'),
                ]
            );
            $this->outputTrackingPixel();
            return;
        }

        $snapshotTable = $wpdb->prefix . 'bressol_esp_campaign_audience_snapshot';
        $snapshot = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT campaign_id, email FROM {$snapshotTable} WHERE tracking_key = %s LIMIT 1",
                $trackingKey
            )
        );

        if ($snapshot) {
            $wpdb->insert(
                $eventsTable,
                [
                    'campaign_id' => $snapshot->campaign_id,
                    'manual_email_id' => null,
                    'user_id' => null,
                    'recipient_email' => $snapshot->email,
                    'event_type' => 'open',
                    'url' => null,
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        $this->outputTrackingPixel();
    }

    private function outputTrackingPixel(): void
    {
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
        exit;
    }

    public function handleClickTracking(): void
    {
        if (!isset($_GET['bressol_esp_click'], $_GET['tracking_key'], $_GET['target'])) {
            return;
        }

        $trackingKey = sanitize_text_field(wp_unslash($_GET['tracking_key']));
        $target = sanitize_text_field(wp_unslash($_GET['target']));

        if ($trackingKey === '' || $target === '') {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $targetUrl = $this->decodeTrackedUrl($target);
        if ($targetUrl === '') {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        global $wpdb;
        $manualEmailsTable = $wpdb->prefix . 'bressol_esp_manual_emails';
        $eventsTable = $wpdb->prefix . 'bressol_esp_events';

        $manualEmail = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, recipient_email FROM {$manualEmailsTable} WHERE tracking_key = %s LIMIT 1",
                $trackingKey
            )
        );

        if ($manualEmail) {
            $wpdb->insert(
                $eventsTable,
                [
                    'campaign_id' => null,
                    'manual_email_id' => $manualEmail->id,
                    'user_id' => null,
                    'recipient_email' => $manualEmail->recipient_email,
                    'event_type' => 'click',
                    'url' => $targetUrl,
                    'created_at' => current_time('mysql'),
                ]
            );
            wp_safe_redirect($targetUrl);
            exit;
        }

        $snapshotTable = $wpdb->prefix . 'bressol_esp_campaign_audience_snapshot';
        $snapshot = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT campaign_id, email FROM {$snapshotTable} WHERE tracking_key = %s LIMIT 1",
                $trackingKey
            )
        );

        if ($snapshot) {
            $wpdb->insert(
                $eventsTable,
                [
                    'campaign_id' => $snapshot->campaign_id,
                    'manual_email_id' => null,
                    'user_id' => null,
                    'recipient_email' => $snapshot->email,
                    'event_type' => 'click',
                    'url' => $targetUrl,
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        wp_safe_redirect($targetUrl);
        exit;
    }

    public function handleUnsubscribe(): void
    {
        if (!isset($_GET['bressol_esp_unsub'], $_GET['tracking_key'])) {
            return;
        }

        $trackingKey = sanitize_text_field(wp_unslash($_GET['tracking_key']));
        if ($trackingKey === '') {
            wp_die('No se pudo procesar la baja.');
        }

        global $wpdb;
        $manualEmailsTable = $wpdb->prefix . 'bressol_esp_manual_emails';
        $eventsTable = $wpdb->prefix . 'bressol_esp_events';
        $consentsTable = $wpdb->prefix . 'bressol_esp_consents';

        $manualEmail = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, recipient_email FROM {$manualEmailsTable} WHERE tracking_key = %s LIMIT 1",
                $trackingKey
            )
        );

        $snapshotTable = $wpdb->prefix . 'bressol_esp_campaign_audience_snapshot';
        $snapshot = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT campaign_id, email FROM {$snapshotTable} WHERE tracking_key = %s LIMIT 1",
                $trackingKey
            )
        );

        if (!$manualEmail && !$snapshot) {
            wp_die('No se pudo procesar la baja.');
        }

        $recipientEmail = $manualEmail ? $manualEmail->recipient_email : $snapshot->email;
        $now = current_time('mysql');
        $wpdb->replace(
            $consentsTable,
            [
                'user_id' => null,
                'email' => $recipientEmail,
                'status' => 'opt_out',
                'source' => 'manual_unsubscribe',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        $wpdb->insert(
            $eventsTable,
            [
                'campaign_id' => $snapshot ? $snapshot->campaign_id : null,
                'manual_email_id' => $manualEmail ? $manualEmail->id : null,
                'user_id' => null,
                'recipient_email' => $recipientEmail,
                'event_type' => 'unsub',
                'url' => null,
                'created_at' => $now,
            ]
        );

        $this->insertAuditLog(
            'unsubscribe',
            'consent',
            0,
            [
                'recipient_email' => $recipientEmail,
                'source' => 'manual_unsubscribe',
                'campaign_id' => $snapshot ? $snapshot->campaign_id : null,
                'manual_email_id' => $manualEmail ? $manualEmail->id : null,
            ]
        );

        wp_die('Te has dado de baja correctamente.');
    }

    public function processSendQueue(): void
    {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }

        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';
        $jobsTable = $wpdb->prefix . 'bressol_esp_send_queue_jobs';

        $now = current_time('mysql');
        $job = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, queue_id, attempts
                 FROM {$jobsTable}
                 WHERE status = %s AND run_at <= %s
                 ORDER BY run_at ASC
                 LIMIT 1",
                'scheduled',
                $now
            )
        );

        if (!$job) {
            return;
        }

        $claimed = $wpdb->update(
            $jobsTable,
            [
                'status' => 'running',
                'updated_at' => $now,
            ],
            [
                'id' => $job->id,
                'status' => 'scheduled',
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
                '%s',
            ]
        );

        if ($claimed === 0) {
            return;
        }

        $queueItem = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, campaign_id, manual_email_id, recipient_email, status, attempts
                 FROM {$queueTable}
                 WHERE id = %d",
                $job->queue_id
            )
        );

        if (!$queueItem) {
            $this->markJobFailed((int) $job->id, 'Item de cola no encontrado.');
            return;
        }

        $queueUpdated = $wpdb->update(
            $queueTable,
            [
                'status' => 'sending',
            ],
            [
                'id' => $queueItem->id,
                'status' => 'pending',
            ],
            [
                '%s',
            ],
            [
                '%d',
                '%s',
            ]
        );

        if ($queueUpdated === 0) {
            $this->markJobFailed((int) $job->id, 'No se pudo marcar el envío como sending.');
            return;
        }

        $sent = false;
        $error = '';

        if ($queueItem->manual_email_id) {
            $sent = $this->sendManualEmail((int) $queueItem->manual_email_id, $queueItem->recipient_email, $error);
        } elseif ($queueItem->campaign_id) {
            $sent = $this->sendCampaignEmail((int) $queueItem->campaign_id, $queueItem->recipient_email, $error);
        } else {
            $error = 'No se encontró un envío asociado.';
        }

        if ($sent) {
            $wpdb->update(
                $queueTable,
                [
                    'status' => 'sent',
                    'sent_at' => $now,
                    'attempts' => (int) $queueItem->attempts + 1,
                    'last_error' => null,
                ],
                [
                    'id' => $queueItem->id,
                ],
                [
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            $wpdb->update(
                $jobsTable,
                [
                    'status' => 'completed',
                    'updated_at' => $now,
                    'attempts' => (int) $job->attempts + 1,
                ],
                [
                    'id' => $job->id,
                ],
                [
                    '%s',
                    '%s',
                    '%d',
                ],
                [
                    '%d',
                ]
            );

            return;
        }

        $nextAttempts = (int) $job->attempts + 1;
        if ($nextAttempts < self::MAX_QUEUE_ATTEMPTS) {
            $retryAt = gmdate('Y-m-d H:i:s', strtotime($now . ' UTC') + (self::RETRY_MINUTES * 60));

            $wpdb->update(
                $queueTable,
                [
                    'status' => 'pending',
                    'attempts' => (int) $queueItem->attempts + 1,
                    'last_error' => $error,
                ],
                [
                    'id' => $queueItem->id,
                ],
                [
                    '%s',
                    '%d',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            $wpdb->update(
                $jobsTable,
                [
                    'status' => 'scheduled',
                    'run_at' => $retryAt,
                    'attempts' => $nextAttempts,
                    'last_error' => $error,
                    'updated_at' => $now,
                ],
                [
                    'id' => $job->id,
                ],
                [
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            return;
        }

        $wpdb->update(
            $queueTable,
            [
                'status' => 'failed',
                'attempts' => (int) $queueItem->attempts + 1,
                'last_error' => $error,
            ],
            [
                'id' => $queueItem->id,
            ],
            [
                '%s',
                '%d',
                '%s',
            ],
            [
                '%d',
            ]
        );

        $this->markJobFailed((int) $job->id, $error, $nextAttempts);
    }

    public function automateCampaigns(): void
    {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }

        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';
        $jobsTable = $wpdb->prefix . 'bressol_esp_send_queue_jobs';
        $now = current_time('mysql');

        $scheduledCampaigns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, scheduled_at FROM {$campaignsTable} WHERE status = %s AND scheduled_at IS NOT NULL AND scheduled_at <= %s",
                'scheduled',
                $now
            )
        );

        foreach ($scheduledCampaigns as $campaign) {
            $queueCount = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$queueTable} WHERE campaign_id = %d", $campaign->id)
            );

            $jobCount = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$jobsTable} j
                     INNER JOIN {$queueTable} q ON q.id = j.queue_id
                     WHERE q.campaign_id = %d",
                    $campaign->id
                )
            );

            $snapshotCount = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}bressol_esp_campaign_audience_snapshot WHERE campaign_id = %d",
                    $campaign->id
                )
            );

            if ($queueCount === 0 || $snapshotCount === 0 || $jobCount === 0) {
                continue;
            }

            $wpdb->update(
                $campaignsTable,
                [
                    'status' => 'sending',
                    'updated_at' => $now,
                ],
                [
                    'id' => $campaign->id,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );
        }

        $sendingCampaigns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$campaignsTable} WHERE status = %s",
                'sending'
            )
        );

        foreach ($sendingCampaigns as $campaign) {
            $totalQueue = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$queueTable} WHERE campaign_id = %d", $campaign->id)
            );

            if ($totalQueue === 0) {
                continue;
            }

            $pendingQueue = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$queueTable} WHERE campaign_id = %d AND status = %s",
                    $campaign->id,
                    'pending'
                )
            );

            $activeJobs = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$jobsTable} j
                     INNER JOIN {$queueTable} q ON q.id = j.queue_id
                     WHERE q.campaign_id = %d AND j.status IN ('scheduled', 'running')",
                    $campaign->id
                )
            );

            if ($pendingQueue === 0 && $activeJobs === 0) {
                $wpdb->update(
                    $campaignsTable,
                    [
                        'status' => 'finished',
                        'updated_at' => $now,
                    ],
                    [
                        'id' => $campaign->id,
                    ],
                    [
                        '%s',
                        '%s',
                    ],
                    [
                        '%d',
                    ]
                );
            }
        }
    }

    private function enqueueCampaignSnapshot(int $campaignId, string $scheduledAt): void
    {
        global $wpdb;
        $snapshotTable = $wpdb->prefix . 'bressol_esp_campaign_audience_snapshot';
        $queueTable = $wpdb->prefix . 'bressol_esp_send_queue';
        $jobsTable = $wpdb->prefix . 'bressol_esp_send_queue_jobs';

        $scheduledAtValue = $scheduledAt !== '' ? $scheduledAt : current_time('mysql');
        $now = current_time('mysql');

        $recipients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT email FROM {$snapshotTable} WHERE campaign_id = %d",
                $campaignId
            )
        );

        if (empty($recipients)) {
            return;
        }

        foreach ($recipients as $recipient) {
            $email = $recipient->email;
            if (!$email) {
                continue;
            }

            $queueId = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$queueTable} WHERE campaign_id = %d AND recipient_email = %s LIMIT 1",
                    $campaignId,
                    $email
                )
            );

            if ($queueId > 0) {
                continue;
            }

            $inserted = $wpdb->insert(
                $queueTable,
                [
                    'campaign_id' => $campaignId,
                    'manual_email_id' => null,
                    'recipient_email' => $email,
                    'status' => 'pending',
                    'attempts' => 0,
                    'scheduled_at' => $scheduledAtValue,
                    'sent_at' => null,
                    'last_error' => null,
                    'created_at' => $now,
                ],
                [
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($inserted === false) {
                continue;
            }

            $queueId = (int) $wpdb->insert_id;
            if ($queueId <= 0) {
                continue;
            }

            $wpdb->insert(
                $jobsTable,
                [
                    'queue_id' => $queueId,
                    'status' => 'scheduled',
                    'run_at' => $scheduledAtValue,
                    'attempts' => 0,
                    'last_error' => null,
                    'created_at' => $now,
                    'updated_at' => null,
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
        }
    }

    private function sendManualEmail(int $manualEmailId, string $recipientEmail, string &$error): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_esp_manual_emails';
        $consentsTable = $wpdb->prefix . 'bressol_esp_consents';

        $manualEmail = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT subject, html_body FROM {$table} WHERE id = %d",
                $manualEmailId
            )
        );

        if (!$manualEmail) {
            $error = 'Email manual no encontrado.';
            return false;
        }

        $consentStatus = $wpdb->get_var(
            $wpdb->prepare("SELECT status FROM {$consentsTable} WHERE email = %s LIMIT 1", $recipientEmail)
        );
        if ($consentStatus === 'opt_out') {
            $error = 'El destinatario está en opt-out.';
            return false;
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $sent = wp_mail($recipientEmail, $manualEmail->subject, $manualEmail->html_body, $headers);

        if ($sent) {
            $wpdb->update(
                $table,
                [
                    'sent_at' => current_time('mysql'),
                ],
                [
                    'id' => $manualEmailId,
                ],
                [
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            $this->insertAuditLog(
                'manual_email_sent',
                'manual_email',
                $manualEmailId,
                [
                    'recipient_email' => $recipientEmail,
                ]
            );

            return true;
        }

        $this->insertAuditLog(
            'manual_email_send_failed',
            'manual_email',
            $manualEmailId,
            [
                'recipient_email' => $recipientEmail,
            ]
        );

        if (!$sent) {
            $error = 'Fallo en el envío SMTP.';
        }

        return false;
    }

    private function sendCampaignEmail(int $campaignId, string $recipientEmail, string &$error): bool
    {
        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $templatesTable = $wpdb->prefix . 'bressol_esp_templates';
        $consentsTable = $wpdb->prefix . 'bressol_esp_consents';
        $snapshotTable = $wpdb->prefix . 'bressol_esp_campaign_audience_snapshot';

        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, template_id, template_snapshot FROM {$campaignsTable} WHERE id = %d",
                $campaignId
            )
        );

        if (!$campaign) {
            $error = 'Campaña no encontrada.';
            return false;
        }

        $consentStatus = $wpdb->get_var(
            $wpdb->prepare("SELECT status FROM {$consentsTable} WHERE email = %s LIMIT 1", $recipientEmail)
        );
        if ($consentStatus === 'opt_out') {
            $error = 'El destinatario está en opt-out.';
            return false;
        }

        $templateId = (int) $campaign->template_id;
        $snapshotLanguage = null;
        $snapshotTrackingKey = null;

        $snapshotRow = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT tracking_key, language FROM {$snapshotTable} WHERE campaign_id = %d AND email = %s LIMIT 1",
                $campaignId,
                $recipientEmail
            )
        );

        if ($snapshotRow) {
            $snapshotTrackingKey = $snapshotRow->tracking_key ?: null;
            $snapshotLanguage = $snapshotRow->language ?: null;
        }

        if ($campaign->template_snapshot) {
            $snapshot = json_decode($campaign->template_snapshot, true);
            if (is_array($snapshot) && !empty($snapshot['templates'])) {
                $languageKey = $snapshotLanguage ?: ($snapshot['default_language'] ?? null);
                if ($languageKey && !empty($snapshot['templates'][$languageKey])) {
                    $templateId = (int) $snapshot['templates'][$languageKey];
                }
            }
        }

        if ($templateId === 0) {
            $error = 'Plantilla no encontrada.';
            return false;
        }

        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT html, text_plain FROM {$templatesTable} WHERE id = %d",
                $templateId
            )
        );

        if (!$template) {
            $error = 'Plantilla no encontrada.';
            return false;
        }

        $trackingKey = $snapshotTrackingKey ?: wp_generate_password(32, false, false);
        if (!$snapshotTrackingKey) {
            $wpdb->update(
                $snapshotTable,
                [
                    'tracking_key' => $trackingKey,
                ],
                [
                    'campaign_id' => $campaignId,
                    'email' => $recipientEmail,
                ],
                [
                    '%s',
                ],
                [
                    '%d',
                    '%s',
                ]
            );
        }

        $subject = $campaign->name;
        $bodyHtml = $template->html;
        $bodyHtml = $this->rewriteCampaignLinks($bodyHtml, $trackingKey);
        $bodyHtml = $this->appendCampaignOpenPixel($bodyHtml, $trackingKey);
        $bodyHtml = $this->appendCampaignUnsubscribe($bodyHtml, $trackingKey);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $sent = wp_mail($recipientEmail, $subject, $bodyHtml, $headers);

        if (!$sent) {
            $error = 'Fallo en el envío SMTP.';
        }

        return $sent;
    }

    private function rewriteCampaignLinks(string $htmlBody, string $trackingKey): string
    {
        if ($htmlBody === '') {
            return $htmlBody;
        }

        $previousErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlBody);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            return $htmlBody;
        }

        $links = $dom->getElementsByTagName('a');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            $link->setAttribute('href', $this->buildCampaignClickTrackingUrl($trackingKey, $href));
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return $htmlBody;
        }

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output !== '' ? $output : $htmlBody;
    }

    private function buildCampaignClickTrackingUrl(string $trackingKey, string $targetUrl): string
    {
        $encodedTarget = $this->encodeUrlForTracking($targetUrl);

        return add_query_arg(
            [
                'bressol_esp_click' => '1',
                'tracking_key' => $trackingKey,
                'target' => $encodedTarget,
            ],
            home_url('/')
        );
    }

    private function appendCampaignOpenPixel(string $htmlBody, string $trackingKey): string
    {
        $trackingUrl = add_query_arg(
            [
                'bressol_esp_open' => '1',
                'tracking_key' => $trackingKey,
            ],
            home_url('/')
        );

        $pixel = '<img src="' . esc_url($trackingUrl) . '" alt="" width="1" height="1" style="display:none;" />';

        return $htmlBody . $pixel;
    }

    private function appendCampaignUnsubscribe(string $htmlBody, string $trackingKey): string
    {
        $unsubscribeUrl = add_query_arg(
            [
                'bressol_esp_unsub' => '1',
                'tracking_key' => $trackingKey,
            ],
            home_url('/')
        );

        $htmlLink = '<p style="font-size:12px;color:#666;">Si no quieres recibir más mensajes, puedes darte de baja aquí: '
            . '<a href="' . esc_url($unsubscribeUrl) . '">darte de baja</a>.</p>';

        return $htmlBody . $htmlLink;
    }

    private function encodeUrlForTracking(string $targetUrl): string
    {
        $encoded = base64_encode($targetUrl);
        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    private function markJobFailed(int $jobId, string $error, int $attempts = 1): void
    {
        global $wpdb;
        $jobsTable = $wpdb->prefix . 'bressol_esp_send_queue_jobs';

        $wpdb->update(
            $jobsTable,
            [
                'status' => 'failed',
                'last_error' => $error,
                'attempts' => $attempts,
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $jobId,
            ],
            [
                '%s',
                '%s',
                '%d',
                '%s',
            ],
            [
                '%d',
            ]
        );
    }

    private function decodeTrackedUrl(string $encoded): string
    {
        $base64 = strtr($encoded, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return '';
        }

        $decoded = trim($decoded);
        if ($decoded === '' || !filter_var($decoded, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $decoded;
    }

    private function insertAuditLog(string $action, string $targetType, int $targetId, array $metadata = []): void
    {
        global $wpdb;
        $auditTable = $wpdb->prefix . 'bressol_esp_audit_logs';

        $wpdb->insert(
            $auditTable,
            [
                'actor_user_id' => null,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata' => $metadata ? wp_json_encode($metadata) : null,
                'created_at' => current_time('mysql'),
            ]
        );
    }

    public function registerAdminMenu(): void
    {
        $capability = 'manage_options';

        add_submenu_page(
            'bressol',
            'Bressol ESP',
            'Bressol ESP',
            $capability,
            'bressol-esp',
            [AdminPages::class, 'renderOverview']
        );

        add_submenu_page(
            'bressol-esp',
            'Campañas',
            'Campañas',
            $capability,
            'bressol-esp-campaigns',
            [AdminPages::class, 'renderCampaigns']
        );

        add_submenu_page(
            'bressol-esp',
            'Plantillas',
            'Plantillas',
            $capability,
            'bressol-esp-templates',
            [AdminPages::class, 'renderTemplates']
        );

        add_submenu_page(
            'bressol-esp',
            'Segmentos',
            'Segmentos',
            $capability,
            'bressol-esp-segments',
            [AdminPages::class, 'renderSegments']
        );

        add_submenu_page(
            'bressol-esp',
            'Emails manuales',
            'Emails manuales',
            $capability,
            'bressol-esp-manual',
            [AdminPages::class, 'renderManualEmails']
        );

        add_submenu_page(
            'bressol-esp',
            'Métricas',
            'Métricas',
            $capability,
            'bressol-esp-metrics',
            [AdminPages::class, 'renderMetrics']
        );

        add_submenu_page(
            'bressol-esp',
            'Consentimientos',
            'Consentimientos',
            $capability,
            'bressol-esp-consents',
            [AdminPages::class, 'renderConsents']
        );

        add_submenu_page(
            'bressol-esp',
            'Exportaciones',
            'Exportaciones',
            $capability,
            'bressol-esp-exports',
            [AdminPages::class, 'renderExports']
        );

        add_submenu_page(
            'bressol-esp',
            'Cola de envíos',
            'Cola de envíos',
            $capability,
            'bressol-esp-queue',
            [AdminPages::class, 'renderQueue']
        );

        add_submenu_page(
            'bressol-esp',
            'Configuración SMTP',
            'Configuración SMTP',
            $capability,
            'bressol-esp-settings',
            [AdminPages::class, 'renderSettings']
        );

        add_submenu_page(
            'bressol-esp',
            'Auditoría',
            'Auditoría',
            $capability,
            'bressol-esp-audit',
            [AdminPages::class, 'renderAudit']
        );
    }
}
