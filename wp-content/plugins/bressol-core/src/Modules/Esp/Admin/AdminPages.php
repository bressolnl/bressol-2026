<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Admin;

use Bressol\Modules\Esp\Services\Capabilities;
use Bressol\Modules\Esp\Services\AuditLogger;
use Bressol\Modules\Esp\Services\QueueService;
use Bressol\Modules\Esp\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    public function registerMenus(): void
    {
        $capability = Capabilities::CAP;

        $this->ensure_parent_menu_exists();

        add_submenu_page(
            'bressol',
            'ESP',
            'ESP',
            $capability,
            'bressol_esp',
            [$this, 'renderHome']
        );

        add_submenu_page(
            'bressol',
            'ESP - Campañas',
            'Campañas',
            $capability,
            'bressol_esp_campaigns',
            [$this, 'renderCampaigns']
        );

        add_submenu_page(
            'bressol',
            'ESP - Nueva campaña',
            'Nueva campaña',
            $capability,
            'bressol_esp_campaign_edit',
            [$this, 'renderCampaignEdit']
        );

        add_submenu_page(
            'bressol',
            'ESP - Listas',
            'Listas',
            $capability,
            'bressol_esp_lists',
            [$this, 'renderLists']
        );

        add_submenu_page(
            'bressol',
            'ESP - Cola',
            'Cola',
            $capability,
            'bressol_esp_queue',
            [$this, 'renderQueue']
        );

        add_submenu_page(
            'bressol',
            'ESP - Configuración',
            'Configuración',
            $capability,
            'bressol_esp_settings',
            [$this, 'renderSettings']
        );

        add_submenu_page(
            'bressol',
            'Diagnóstico ESP',
            'Diagnóstico',
            $capability,
            'bressol_esp_diagnostics',
            [DiagnosticsPage::class, 'render']
        );
    }

    public function renderHome(): void
    {
        $this->ensureAccess();
        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $listsTable = $wpdb->prefix . 'bressol_esp_lists';
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';

        $campaignsCount = $this->table_exists($campaignsTable) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$campaignsTable}") : null;
        $listsCount = $this->table_exists($listsTable) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$listsTable}") : null;
        $pendingCount = $this->table_exists($queueTable) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$queueTable} WHERE status = 'pending'") : null;
        $sentToday = null;
        if ($this->table_exists($queueTable)) {
            $todayStart = date('Y-m-d 00:00:00', current_time('timestamp'));
            $sentToday = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$queueTable} WHERE status = 'sent' AND sent_at >= %s",
                    $todayStart
                )
            );
        }

        echo '<div class="wrap"><h1>ESP</h1>';
        echo '<table class="widefat striped" style="max-width:700px;">';
        echo '<tbody>';
        echo '<tr><th>Campañas</th><td>' . esc_html($campaignsCount !== null ? (string) $campaignsCount : 'N/D') . '</td></tr>';
        echo '<tr><th>Listas</th><td>' . esc_html($listsCount !== null ? (string) $listsCount : 'N/D') . '</td></tr>';
        echo '<tr><th>En cola (pending)</th><td>' . esc_html($pendingCount !== null ? (string) $pendingCount : 'N/D') . '</td></tr>';
        echo '<tr><th>Enviados hoy</th><td>' . esc_html($sentToday !== null ? (string) $sentToday : 'N/D') . '</td></tr>';
        echo '</tbody></table></div>';
    }

    public function renderSettings(): void
    {
        $this->ensureAccess();
        $settingsService = new Settings();
        if (isset($_POST['bressol_esp_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bressol_esp_settings_nonce'])), 'bressol_esp_settings_save')) {
            $settingsService->save($_POST);
            echo '<div class="notice notice-success"><p>Configuración guardada.</p></div>';
        }
        $settings = $settingsService->get_settings();

        echo '<div class="wrap"><h1>Configuración ESP</h1>';
        echo '<form method="post">';
        echo '<input type="hidden" name="bressol_esp_settings_nonce" value="' . esc_attr(wp_create_nonce('bressol_esp_settings_save')) . '" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Enabled</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked(true, (bool) $settings['enabled'], false) . ' /> Activar SMTP</label></td></tr>';
        echo '<tr><th>From name</th><td><input type="text" name="from_name" value="' . esc_attr((string) $settings['from_name']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>From email</th><td><input type="email" name="from_email" value="' . esc_attr((string) $settings['from_email']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Reply-to</th><td><input type="email" name="reply_to" value="' . esc_attr((string) $settings['reply_to']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>SMTP host</th><td><input type="text" name="smtp_host" value="' . esc_attr((string) $settings['smtp_host']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>SMTP port</th><td><input type="number" name="smtp_port" value="' . esc_attr((string) $settings['smtp_port']) . '" /></td></tr>';
        echo '<tr><th>SMTP user</th><td><input type="text" name="smtp_user" value="' . esc_attr((string) $settings['smtp_user']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>SMTP pass</th><td><input type="password" name="smtp_pass" value="' . esc_attr((string) $settings['smtp_pass']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>SMTP secure</th><td><select name="smtp_secure">';
        foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($value, (string) $settings['smtp_secure'], false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>Daily cap</th><td><input type="number" name="daily_cap" value="' . esc_attr((string) $settings['daily_cap']) . '" /></td></tr>';
        echo '<tr><th>Batch size</th><td><input type="number" name="batch_size" value="' . esc_attr((string) $settings['batch_size']) . '" /></td></tr>';
        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary">Guardar</button></p>';
        echo '</form></div>';
    }

    public function renderCampaigns(): void
    {
        $this->ensureAccess();
        global $wpdb;

        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        if (!$this->table_exists($campaignsTable)) {
            $this->renderMissingTablesNotice();
            return;
        }

        $action = isset($_GET['esp_action']) ? sanitize_key(wp_unslash($_GET['esp_action'])) : '';
        $campaignId = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if ($action !== '' && $campaignId > 0 && wp_verify_nonce($nonce, 'bressol_esp_campaign_action')) {
            $queueService = new QueueService();
            if ($action === 'send_now') {
                $wpdb->update($campaignsTable, ['status' => 'sending'], ['id' => $campaignId], ['%s'], ['%d']);
                $queueService->enqueue_campaign($campaignId);
            } elseif ($action === 'schedule') {
                $scheduledAt = isset($_GET['scheduled_at']) ? sanitize_text_field(wp_unslash($_GET['scheduled_at'])) : '';
                $wpdb->update($campaignsTable, ['status' => 'scheduled', 'scheduled_at' => $scheduledAt], ['id' => $campaignId], ['%s', '%s'], ['%d']);
            } elseif ($action === 'pause') {
                $jobId = $this->getJobIdByCampaign($campaignId);
                if ($jobId > 0) {
                    $queueService->pause_job($jobId);
                    $wpdb->update($campaignsTable, ['status' => 'paused'], ['id' => $campaignId], ['%s'], ['%d']);
                }
            } elseif ($action === 'resume') {
                $jobId = $this->getJobIdByCampaign($campaignId);
                if ($jobId > 0) {
                    $queueService->resume_job($jobId);
                    $wpdb->update($campaignsTable, ['status' => 'sending'], ['id' => $campaignId], ['%s'], ['%d']);
                }
            }
        }

        $campaigns = $wpdb->get_results("SELECT * FROM {$campaignsTable} ORDER BY id DESC");
        $newUrl = add_query_arg('page', 'bressol_esp_campaign_edit', admin_url('admin.php'));

        echo '<div class="wrap"><h1>Campañas <a class="page-title-action" href="' . esc_url($newUrl) . '">Nueva</a></h1>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Nombre</th><th>Status</th><th>List</th><th>Programado</th><th>Acciones</th></tr></thead><tbody>';
        if (!$campaigns) {
            echo '<tr><td colspan="6">Sin campañas.</td></tr>';
        } else {
            foreach ($campaigns as $campaign) {
                $editUrl = add_query_arg(
                    [
                        'page' => 'bressol_esp_campaign_edit',
                        'campaign_id' => (int) $campaign->id,
                    ],
                    admin_url('admin.php')
                );
                $actionBase = [
                    'page' => 'bressol_esp_campaigns',
                    'campaign_id' => (int) $campaign->id,
                    '_wpnonce' => wp_create_nonce('bressol_esp_campaign_action'),
                ];
                $sendNowUrl = add_query_arg($actionBase + ['esp_action' => 'send_now'], admin_url('admin.php'));
                $pauseUrl = add_query_arg($actionBase + ['esp_action' => 'pause'], admin_url('admin.php'));
                $resumeUrl = add_query_arg($actionBase + ['esp_action' => 'resume'], admin_url('admin.php'));
                echo '<tr>';
                echo '<td>' . esc_html((string) $campaign->id) . '</td>';
                echo '<td><a href="' . esc_url($editUrl) . '">' . esc_html((string) $campaign->name) . '</a></td>';
                echo '<td>' . esc_html((string) $campaign->status) . '</td>';
                echo '<td>' . esc_html((string) $campaign->list_id) . '</td>';
                echo '<td>' . esc_html((string) ($campaign->scheduled_at ?? '')) . '</td>';
                echo '<td><a href="' . esc_url($sendNowUrl) . '">Enviar ahora</a> | <a href="' . esc_url($pauseUrl) . '">Pausar</a> | <a href="' . esc_url($resumeUrl) . '">Reanudar</a></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    public function renderCampaignEdit(): void
    {
        $this->ensureAccess();
        global $wpdb;
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';
        $listsTable = $wpdb->prefix . 'bressol_esp_lists';

        if (!$this->table_exists($campaignsTable)) {
            $this->renderMissingTablesNotice();
            return;
        }

        $campaignId = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;
        $campaign = null;
        if ($campaignId > 0) {
            $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$campaignsTable} WHERE id = %d LIMIT 1", $campaignId));
        }

        if (isset($_POST['bressol_esp_campaign_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bressol_esp_campaign_nonce'])), 'bressol_esp_campaign_save')) {
            $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
            $subject = sanitize_text_field((string) ($_POST['subject'] ?? ''));
            $htmlBody = wp_kses_post((string) ($_POST['html_body'] ?? ''));
            $listId = absint($_POST['list_id'] ?? 0);
            $scheduledAt = sanitize_text_field((string) ($_POST['scheduled_at'] ?? ''));
            $now = current_time('mysql');

            $status = $scheduledAt !== '' ? 'scheduled' : 'draft';
            if ($campaign) {
                $wpdb->update(
                    $campaignsTable,
                    [
                        'name' => $name,
                        'subject' => $subject,
                        'html_body' => $htmlBody,
                        'list_id' => $listId,
                        'status' => $status,
                        'scheduled_at' => $scheduledAt !== '' ? $scheduledAt : null,
                        'updated_at' => $now,
                    ],
                    ['id' => $campaignId],
                    ['%s','%s','%s','%d','%s','%s','%s'],
                    ['%d']
                );
                (new AuditLogger())->log('campaign_updated', 'campaign', $campaignId);
            } else {
                $wpdb->insert(
                    $campaignsTable,
                    [
                        'name' => $name,
                        'subject' => $subject,
                        'html_body' => $htmlBody,
                        'list_id' => $listId,
                        'status' => $status,
                        'scheduled_at' => $scheduledAt !== '' ? $scheduledAt : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%s','%s','%s','%d','%s','%s','%s','%s']
                );
                $campaignId = (int) $wpdb->insert_id;
                (new AuditLogger())->log('campaign_created', 'campaign', $campaignId);
            }
            if ($status === 'scheduled') {
                (new QueueService())->enqueue_campaign($campaignId);
            }
            $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$campaignsTable} WHERE id = %d LIMIT 1", $campaignId));
        }

        if (isset($_POST['bressol_esp_test_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bressol_esp_test_nonce'])), 'bressol_esp_campaign_test') && $campaign) {
            $testEmail = sanitize_email((string) ($_POST['test_email'] ?? ''));
            if ($testEmail !== '' && is_email($testEmail)) {
                Settings::set_smtp_allowed(true);
                try {
                    $sent = wp_mail($testEmail, (string) $campaign->subject, (string) $campaign->html_body, ['Content-Type: text/html; charset=UTF-8']);
                } finally {
                    Settings::set_smtp_allowed(false);
                }
                if ($sent) {
                    (new AuditLogger())->log('test_sent', 'campaign', (int) $campaign->id, ['email' => $testEmail]);
                    echo '<div class="notice notice-success"><p>Test enviado.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>No se pudo enviar el test.</p></div>';
                }
            }
        }

        $lists = $wpdb->get_results("SELECT * FROM {$listsTable} ORDER BY id DESC");

        echo '<div class="wrap"><h1>' . esc_html($campaign ? 'Editar campaña' : 'Nueva campaña') . '</h1>';
        echo '<form method="post">';
        echo '<input type="hidden" name="bressol_esp_campaign_nonce" value="' . esc_attr(wp_create_nonce('bressol_esp_campaign_save')) . '" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Nombre</th><td><input type="text" name="name" value="' . esc_attr((string) ($campaign->name ?? '')) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Asunto</th><td><input type="text" name="subject" value="' . esc_attr((string) ($campaign->subject ?? '')) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Lista</th><td><select name="list_id">';
        foreach ((array) $lists as $list) {
            echo '<option value="' . esc_attr((string) $list->id) . '" ' . selected((string) ($campaign->list_id ?? ''), (string) $list->id, false) . '>' . esc_html((string) $list->name) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>Programado</th><td><input type="text" name="scheduled_at" value="' . esc_attr((string) ($campaign->scheduled_at ?? '')) . '" placeholder="YYYY-MM-DD HH:MM:SS" class="regular-text" /></td></tr>';
        echo '<tr><th>HTML</th><td><textarea name="html_body" rows="10" cols="60" class="large-text code">' . esc_textarea((string) ($campaign->html_body ?? '')) . '</textarea></td></tr>';
        echo '</tbody></table>';
        echo '<p><button class="button button-primary" type="submit">Guardar</button></p>';
        echo '</form>';

        if ($campaign) {
            echo '<h2>Test email</h2>';
            echo '<form method="post">';
            echo '<input type="hidden" name="bressol_esp_test_nonce" value="' . esc_attr(wp_create_nonce('bressol_esp_campaign_test')) . '" />';
            echo '<input type="email" name="test_email" value="" placeholder="test@dominio.com" class="regular-text" />';
            echo '<button class="button" type="submit">Enviar test</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    public function renderLists(): void
    {
        $this->ensureAccess();
        global $wpdb;
        $listsTable = $wpdb->prefix . 'bressol_esp_lists';
        $membersTable = $wpdb->prefix . 'bressol_esp_list_members';

        if (!$this->table_exists($listsTable)) {
            $this->renderMissingTablesNotice();
            return;
        }

        if (isset($_POST['bressol_esp_list_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bressol_esp_list_nonce'])), 'bressol_esp_list_save')) {
            $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
            if ($name !== '') {
                $wpdb->insert(
                    $listsTable,
                    ['name' => $name, 'created_at' => current_time('mysql')],
                    ['%s', '%s']
                );
            }
        }

        $listId = isset($_GET['list_id']) ? absint($_GET['list_id']) : 0;
        if (isset($_POST['bressol_esp_member_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bressol_esp_member_nonce'])), 'bressol_esp_member_add') && $listId > 0) {
            $emailsRaw = (string) ($_POST['emails'] ?? '');
            $emails = preg_split('/[\s,]+/', $emailsRaw) ?: [];
            foreach ($emails as $email) {
                $email = sanitize_email($email);
                if ($email === '' || !is_email($email)) {
                    continue;
                }
                $wpdb->insert(
                    $membersTable,
                    [
                        'list_id' => $listId,
                        'email' => $email,
                        'status' => 'subscribed',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ],
                    ['%d','%s','%s','%s','%s']
                );
            }
        }

        $deleteId = isset($_GET['delete_member']) ? absint($_GET['delete_member']) : 0;
        if ($deleteId > 0 && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'bressol_esp_member_delete')) {
            $wpdb->delete($membersTable, ['id' => $deleteId], ['%d']);
        }

        $lists = $wpdb->get_results("SELECT * FROM {$listsTable} ORDER BY id DESC");

        echo '<div class="wrap"><h1>Listas</h1>';
        echo '<form method="post" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="bressol_esp_list_nonce" value="' . esc_attr(wp_create_nonce('bressol_esp_list_save')) . '" />';
        echo '<input type="text" name="name" placeholder="Nombre de lista" class="regular-text" />';
        echo '<button class="button" type="submit">Crear lista</button>';
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Nombre</th><th>Acciones</th></tr></thead><tbody>';
        foreach ((array) $lists as $list) {
            $listUrl = add_query_arg(['page' => 'bressol_esp_lists', 'list_id' => (int) $list->id], admin_url('admin.php'));
            echo '<tr><td>' . esc_html((string) $list->id) . '</td><td>' . esc_html((string) $list->name) . '</td><td><a href="' . esc_url($listUrl) . '">Ver</a></td></tr>';
        }
        echo '</tbody></table>';

        if ($listId > 0) {
            $members = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$membersTable} WHERE list_id = %d ORDER BY id DESC", $listId));
            echo '<h2>Miembros</h2>';
            echo '<form method="post">';
            echo '<input type="hidden" name="bressol_esp_member_nonce" value="' . esc_attr(wp_create_nonce('bressol_esp_member_add')) . '" />';
            echo '<textarea name="emails" rows="5" cols="60" class="large-text" placeholder="email1@dominio.com, email2@dominio.com"></textarea>';
            echo '<button class="button" type="submit">Añadir emails</button>';
            echo '</form>';
            echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Email</th><th>Status</th><th>Acciones</th></tr></thead><tbody>';
            foreach ((array) $members as $member) {
                $deleteUrl = add_query_arg(
                    [
                        'page' => 'bressol_esp_lists',
                        'list_id' => $listId,
                        'delete_member' => (int) $member->id,
                        '_wpnonce' => wp_create_nonce('bressol_esp_member_delete'),
                    ],
                    admin_url('admin.php')
                );
                echo '<tr><td>' . esc_html((string) $member->id) . '</td><td>' . esc_html((string) $member->email) . '</td><td>' . esc_html((string) $member->status) . '</td><td><a href="' . esc_url($deleteUrl) . '">Eliminar</a></td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function renderQueue(): void
    {
        $this->ensureAccess();
        global $wpdb;
        $jobsTable = $wpdb->prefix . 'bressol_esp_jobs';
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';

        if (!$this->table_exists($jobsTable)) {
            $this->renderMissingTablesNotice();
            return;
        }

        $jobs = $wpdb->get_results("SELECT * FROM {$jobsTable} ORDER BY id DESC");
        echo '<div class="wrap"><h1>Cola</h1>';
        echo '<h2>Jobs</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Campaign</th><th>Status</th><th>Scheduled</th><th>Started</th><th>Finished</th></tr></thead><tbody>';
        foreach ((array) $jobs as $job) {
            echo '<tr><td>' . esc_html((string) $job->id) . '</td><td>' . esc_html((string) $job->campaign_id) . '</td><td>' . esc_html((string) $job->status) . '</td><td>' . esc_html((string) ($job->scheduled_at ?? '')) . '</td><td>' . esc_html((string) ($job->started_at ?? '')) . '</td><td>' . esc_html((string) ($job->finished_at ?? '')) . '</td></tr>';
        }
        echo '</tbody></table>';

        $items = $wpdb->get_results("SELECT * FROM {$queueTable} ORDER BY id DESC LIMIT 50");
        echo '<h2>Últimos items</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Job</th><th>Email</th><th>Status</th><th>Attempts</th><th>Error</th></tr></thead><tbody>';
        foreach ((array) $items as $item) {
            echo '<tr><td>' . esc_html((string) $item->id) . '</td><td>' . esc_html((string) $item->job_id) . '</td><td>' . esc_html((string) $item->email) . '</td><td>' . esc_html((string) $item->status) . '</td><td>' . esc_html((string) $item->attempts) . '</td><td>' . esc_html((string) ($item->last_error ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private function ensureAccess(): void
    {
        if (!current_user_can(Capabilities::CAP)) {
            wp_die('No autorizado.');
        }
    }

    private function renderMissingTablesNotice(): void
    {
        $diagnosticsUrl = add_query_arg('page', 'bressol_esp_diagnostics', admin_url('admin.php'));
        echo '<div class="wrap"><h1>ESP</h1><div class="notice notice-warning"><p>';
        echo esc_html__('ESP no instalado / tablas faltan.', 'bressol-core');
        echo ' <a href="' . esc_url($diagnosticsUrl) . '">' . esc_html__('Ir al diagnóstico ESP', 'bressol-core') . '</a>';
        echo '</p></div></div>';
    }

    private function getJobIdByCampaign(int $campaignId): int
    {
        global $wpdb;
        $jobsTable = $wpdb->prefix . 'bressol_esp_jobs';
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$jobsTable} WHERE campaign_id = %d ORDER BY id DESC LIMIT 1", $campaignId)
        );
    }

    private function ensure_parent_menu_exists(): void
    {
        global $menu;
        $slug = 'bressol';

        foreach ((array) $menu as $item) {
            if (is_array($item) && isset($item[2]) && (string) $item[2] === $slug) {
                return;
            }
        }

        add_menu_page(
            'Bressol',
            'Bressol',
            'manage_options',
            $slug,
            static function (): void {
                echo '<div class="wrap"><h1>Bressol</h1></div>';
            },
            'dashicons-store',
            55
        );
    }

    private function table_exists(string $table): bool
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }
}
