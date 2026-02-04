<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Admin;

use Bressol\Modules\B2B\Repositories\LeadRepository;
use Bressol\Modules\B2B\Repositories\TaskRepository;
use Bressol\Modules\B2B\Services\Capabilities;
use Bressol\Modules\B2B\Services\LeadService;
use Bressol\Modules\B2B\Services\Settings;
use Bressol\Modules\B2B\Services\TaskService;
use Bressol\Modules\B2B\Support\Masking;
use Bressol\Modules\Esp\Services\SenderService;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\B2B\Services\LeadService as B2BLeadService;
use Bressol\Modules\B2B\Services\ReminderService;
use Bressol\Modules\B2B\Repositories\LeadEventsRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private const PAGE_SLUG = 'bressol-b2b';
    private const LEAD_SLUG = 'bressol-b2b-lead';

    private LeadRepository $leads;
    private TaskRepository $tasks;
    private LeadEventsRepository $events;
    private LeadService $leadService;
    private TaskService $taskService;
    private Settings $settings;
    private Capabilities $capabilities;
    /** @var array<string, mixed>|null */
    private ?array $selfTestResult = null;

    public function __construct()
    {
        $this->leads = new LeadRepository();
        $this->tasks = new TaskRepository();
        $this->events = new LeadEventsRepository();
        $this->leadService = new LeadService();
        $this->taskService = new TaskService();
        $this->settings = new Settings();
        $this->capabilities = new Capabilities();
    }

    public function registerMenus(): void
    {
        add_menu_page(
            'B2B',
            'B2B',
            $this->capability(),
            self::PAGE_SLUG,
            [$this, 'renderMainPage'],
            'dashicons-groups',
            58
        );

        add_submenu_page(
            self::PAGE_SLUG,
            'B2B',
            'Leads',
            $this->capability(),
            self::PAGE_SLUG,
            [$this, 'renderMainPage']
        );

        add_submenu_page(
            null,
            'B2B Lead',
            'B2B Lead',
            $this->capability(),
            self::LEAD_SLUG,
            [$this, 'renderLeadPage']
        );
    }

    public function renderMainPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'leads';
        if (!in_array($tab, ['leads', 'tasks', 'settings'], true)) {
            $tab = 'leads';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_diagnostics_actions($tab);
        }

        if ($tab === 'tasks') {
            $this->handle_tasks_actions();
        }

        if ($tab === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_settings_save();
        }

        echo '<div class="wrap">';
        echo '<h1>B2B</h1>';
        $signupUrl = $this->settings->get_signup_page_url();
        if ($signupUrl === '') {
            $signupUrl = home_url('/b2b/signup');
        }
        echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url($signupUrl) . '">Open signup</a></p>';
        if (!empty($_GET['created']) && (string) $_GET['created'] === '1') {
            add_settings_error('bressol_b2b', 'lead_created', 'Lead creado.', 'updated');
        }
        settings_errors('bressol_b2b');
        $this->render_diagnostics_box();
        echo $this->render_tabs($tab);

        if ($tab === 'leads') {
            $this->render_leads_tab();
        } elseif ($tab === 'tasks') {
            $this->render_tasks_tab();
        } else {
            $this->render_settings_tab();
        }

        echo '</div>';
    }

    public function renderLeadPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $leadId = isset($_GET['lead_id']) ? absint($_GET['lead_id']) : 0;
        $lead = $leadId > 0 ? $this->leads->find_by_id($leadId) : null;
        $createdLeadId = 0;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->current_user_can()) {
                wp_die('No autorizado.');
            }
            $action = isset($_POST['bressol_b2b_action']) ? sanitize_key((string) wp_unslash($_POST['bressol_b2b_action'])) : '';
            if ($action === 'save_lead') {
                check_admin_referer('bressol_b2b_lead_save');
                $payload = $this->get_lead_payload_from_request($_POST);
                if ($leadId > 0) {
                    $payload['lead_id'] = $leadId;
                }
                $result = $this->leadService->create_or_update($payload, get_current_user_id(), 'admin');
                if ($result['errors'] !== []) {
                    add_settings_error('bressol_b2b', 'lead_invalid', implode(' ', $result['errors']), 'error');
                } else {
                    $leadId = $result['lead_id'];
                    $lead = $this->leads->find_by_id($leadId);
                    $createdLeadId = $result['is_new'] ? $leadId : 0;
                    add_settings_error('bressol_b2b', 'lead_saved', 'Lead guardado.', 'updated');
                }
            } elseif ($action === 'regen_token') {
                check_admin_referer('bressol_b2b_lead_regen');
                if ($leadId > 0) {
                    if ($this->leadService->regenerate_token($leadId)) {
                        $lead = $this->leads->find_by_id($leadId);
                        add_settings_error('bressol_b2b', 'token_regen', 'Token regenerado.', 'updated');
                    }
                }
            } elseif ($action === 'add_task') {
                check_admin_referer('bressol_b2b_task_add');
                $taskPayload = $this->get_task_payload_from_request($_POST);
                if ($leadId > 0) {
                    $taskId = $this->taskService->create_manual_task(
                        $leadId,
                        $taskPayload['type'],
                        $taskPayload['note'],
                        $taskPayload['assigned_user_id'],
                        $taskPayload['due_at']
                    );
                    if ($taskId > 0) {
                        add_settings_error('bressol_b2b', 'task_added', 'Tarea creada.', 'updated');
                    } else {
                        add_settings_error('bressol_b2b', 'task_failed', 'No se pudo crear la tarea.', 'error');
                    }
                }
            } elseif ($action === 'task_done') {
                check_admin_referer('bressol_b2b_task_done');
                $taskId = isset($_POST['task_id']) ? absint($_POST['task_id']) : 0;
                if ($taskId > 0 && $this->taskService->mark_done($taskId)) {
                    add_settings_error('bressol_b2b', 'task_done', 'Tarea completada.', 'updated');
                }
            }
        }

        $lead = $leadId > 0 ? $this->leads->find_by_id($leadId) : $lead;
        $lead = $lead ?? [
            'email' => '',
            'company_name' => '',
            'contact_name' => '',
            'city' => '',
            'phone' => '',
            'business_type' => '',
            'tier' => $this->default_tier(),
            'status' => 'NEEDS_CONSENT',
            'contact_basis' => 'no_consent',
            'source' => 'admin',
            'source_ref_event_id' => '',
            'interests_json' => '',
            'owner_user_id' => $this->settings->get_default_owner_user_id(),
            'lead_score' => 0,
            'sales_stage' => 'new',
            'next_followup_at' => '',
            'consent_token' => '',
            'consent_token_expires_at' => '',
            'consented_at' => '',
        ];

        echo '<div class="wrap">';
        echo '<h1>' . ($leadId > 0 ? 'Editar lead' : 'Nuevo lead') . '</h1>';
        settings_errors('bressol_b2b');

        echo '<form method="post">';
        wp_nonce_field('bressol_b2b_lead_save');
        echo '<input type="hidden" name="bressol_b2b_action" value="save_lead" />';
        echo '<table class="form-table"><tbody>';
        echo $this->render_text_row('Email', 'email', (string) ($lead['email'] ?? ''), true);
        echo $this->render_text_row('Empresa', 'company_name', (string) ($lead['company_name'] ?? ''), false);
        echo $this->render_text_row('Contacto', 'contact_name', (string) ($lead['contact_name'] ?? ''), false);
        echo $this->render_text_row('Ciudad', 'city', (string) ($lead['city'] ?? ''), false);
        echo $this->render_text_row('Teléfono', 'phone', (string) ($lead['phone'] ?? ''), false);
        echo $this->render_select_row('Business type', 'business_type', (string) ($lead['business_type'] ?? ''), $this->business_type_options());
        echo $this->render_select_row('Tier', 'tier', (string) ($lead['tier'] ?? ''), $this->get_tier_options());
        echo $this->render_select_row('Estado', 'status', (string) ($lead['status'] ?? ''), $this->status_options());
        echo $this->render_select_row('Contact basis', 'contact_basis', (string) ($lead['contact_basis'] ?? ''), $this->contact_basis_options());
        echo $this->render_select_row('Sales stage', 'sales_stage', (string) ($lead['sales_stage'] ?? ''), $this->sales_stage_options());
        echo $this->render_text_row('Next follow-up (YYYY-MM-DD HH:MM:SS)', 'next_followup_at', (string) ($lead['next_followup_at'] ?? ''), false);
        echo $this->render_text_row('Fuente', 'source', (string) ($lead['source'] ?? ''), false);
        echo $this->render_text_row('Evento (MarketsEvents ID)', 'source_ref_event_id', (string) ($lead['source_ref_event_id'] ?? ''), false);
        echo $this->render_textarea_row('Intereses (JSON)', 'interests_json', (string) ($lead['interests_json'] ?? ''), false);
        echo $this->render_select_row('Owner', 'owner_user_id', (string) ($lead['owner_user_id'] ?? ''), $this->get_user_options());
        if ($leadId > 0) {
            $lastEvent = $this->events->get_last_event_types([$leadId]);
            $leadService = new B2BLeadService($this->leads, $this->events);
            $temp = $leadService->compute_temperature(array_merge($lead, [
                'last_event_type' => $lastEvent[$leadId] ?? '',
            ]));
            $badge = $this->render_temperature_badge((string) ($temp['temperature'] ?? ''), !empty($temp['stale']));
            echo '<tr><th>Temperatura</th><td>' . $badge . '</td></tr>';
        }
        echo '<tr><th>Score</th><td>' . esc_html((string) ($lead['lead_score'] ?? 0)) . '</td></tr>';
        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">Guardar</button></p>';
        echo '</form>';

        if ($leadId > 0) {
            echo '<h2>Token consentimiento</h2>';
            $token = (string) ($lead['consent_token'] ?? '');
            $expiresAt = (string) ($lead['consent_token_expires_at'] ?? '');
            $expired = $expiresAt !== '' && strtotime($expiresAt) < current_time('timestamp');
            echo '<p><strong>Token:</strong> ' . esc_html($token) . '</p>';
            echo '<p><strong>Expira:</strong> ' . esc_html($expiresAt ?: '-') . ($expired ? ' <span style="color:#b32d2e">(expirado)</span>' : '') . '</p>';
            echo '<form method="post" style="margin-top:10px;">';
            wp_nonce_field('bressol_b2b_lead_regen');
            echo '<input type="hidden" name="bressol_b2b_action" value="regen_token" />';
            echo '<button type="submit" class="button">Regenerar token</button>';
            echo '</form>';

            if ($token !== '') {
                $catalogUrl = $this->build_public_url('catalog', $token);
                $pricelistUrl = $this->build_public_url('pricelist', $token);
                echo '<h3>Enlaces privados</h3>';
                echo '<p><input type="text" class="regular-text b2b-copy-input" readonly value="' . esc_attr($catalogUrl) . '" /> ';
                echo '<button type="button" class="button b2b-copy-btn" data-url="' . esc_attr($catalogUrl)
                    . '" data-default-label="Copiar enlace catálogo">Copiar enlace catálogo</button></p>';
                echo '<p><input type="text" class="regular-text b2b-copy-input" readonly value="' . esc_attr($pricelistUrl) . '" /> ';
                echo '<button type="button" class="button b2b-copy-btn" data-url="' . esc_attr($pricelistUrl)
                    . '" data-default-label="Copiar enlace pricelist">Copiar enlace pricelist</button></p>';
            }

            $timeline = $this->events->list_by_lead($leadId, 50);
            echo '<h2>Timeline</h2>';
            echo '<table class="widefat striped" style="max-width:840px;"><thead><tr><th>Fecha</th><th>Tipo</th><th>Contexto</th></tr></thead><tbody>';
            if ($timeline === []) {
                echo '<tr><td colspan="3">Sin eventos.</td></tr>';
            } else {
                foreach ($timeline as $event) {
                    $context = (string) ($event['context_json'] ?? '');
                    echo '<tr>';
                    echo '<td>' . esc_html((string) ($event['created_at'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($event['type'] ?? '')) . '</td>';
                    echo '<td><code>' . esc_html($this->sanitize_timeline_context($context)) . '</code></td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';

            $tasks = $this->tasks->list_by_lead($leadId);
            echo '<h2>Tareas</h2>';
            echo '<table class="widefat striped" style="max-width:980px;"><thead><tr><th>ID</th><th>Tipo</th><th>Nota</th><th>Asignado</th><th>Vence</th><th>Estado</th><th>Acción</th></tr></thead><tbody>';
            if ($tasks === []) {
                echo '<tr><td colspan="7">Sin tareas.</td></tr>';
            } else {
                foreach ($tasks as $task) {
                    $taskId = (int) ($task['id'] ?? 0);
                    $status = (string) ($task['status'] ?? '');
                    echo '<tr>';
                    echo '<td>' . esc_html((string) $taskId) . '</td>';
                    echo '<td>' . esc_html((string) ($task['type'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($task['note'] ?? '')) . '</td>';
                    echo '<td>' . esc_html($this->user_label((int) ($task['assigned_user_id'] ?? 0))) . '</td>';
                    echo '<td>' . esc_html((string) ($task['due_at'] ?? '')) . '</td>';
                    echo '<td>' . esc_html($status) . '</td>';
                    echo '<td>';
                    if ($status !== 'done') {
                        echo '<form method="post">';
                        wp_nonce_field('bressol_b2b_task_done');
                        echo '<input type="hidden" name="bressol_b2b_action" value="task_done" />';
                        echo '<input type="hidden" name="task_id" value="' . esc_attr((string) $taskId) . '" />';
                        echo '<button class="button button-small" type="submit">Marcar done</button>';
                        echo '</form>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';

            echo '<h3>Crear tarea</h3>';
            echo '<form method="post">';
            wp_nonce_field('bressol_b2b_task_add');
            echo '<input type="hidden" name="bressol_b2b_action" value="add_task" />';
            echo '<table class="form-table"><tbody>';
            echo $this->render_text_row('Tipo', 'task_type', '', true);
            echo $this->render_text_row('Nota', 'task_note', '', true);
            echo $this->render_text_row('Vence (YYYY-MM-DD HH:MM:SS)', 'task_due_at', current_time('mysql'), true);
            echo $this->render_select_row('Asignado', 'task_assigned_user_id', '', $this->get_user_options());
            echo '</tbody></table>';
            echo '<p class="submit"><button type="submit" class="button">Crear tarea</button></p>';
            echo '</form>';
        }

        if ($createdLeadId > 0) {
            echo '<script>window.dataLayer = window.dataLayer || [];';
            echo 'window.dataLayer.push({event:"b2b_lead_created", lead_id:' . (int) $createdLeadId . ', source:"b2b"});</script>';
        }

        if ($leadId > 0) {
            echo '<script>
                document.addEventListener("click", function(e) {
                    var btn = e.target.closest(".b2b-copy-btn");
                    if (!btn) return;
                    var url = btn.getAttribute("data-url") || "";
                    if (!url) return;
                    var defaultLabel = btn.getAttribute("data-default-label") || btn.textContent || "Copiar enlace";
                    var done = function() {
                        btn.textContent = "Copiado";
                        window.setTimeout(function() {
                            btn.textContent = defaultLabel;
                        }, 2000);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(function() {
                            done();
                        });
                        return;
                    }
                    var input = document.createElement("input");
                    input.value = url;
                    document.body.appendChild(input);
                    input.select();
                    try { document.execCommand("copy"); } catch (e) {}
                    document.body.removeChild(input);
                    done();
                }, {capture: true});
            </script>';
        }

        echo '</div>';
    }

    private function render_leads_tab(): void
    {
        $filters = $this->get_leads_filters($_GET);
        $createUrl = admin_url('admin.php?page=' . self::LEAD_SLUG);

        echo '<p><a class="button button-primary" href="' . esc_url($createUrl) . '">Nuevo lead</a></p>';
        echo $this->render_leads_filters_form($filters);

        $table = new LeadsListTable($this->leads, $filters);
        $table->prepare_items();
        $table->display();
    }

    private function render_tasks_tab(): void
    {
        $filters = $this->get_tasks_filters($_GET);
        echo $this->render_tasks_filters_form($filters);

        $table = new TasksListTable($this->tasks, $filters);
        $table->prepare_items();
        $table->display();
    }

    private function render_settings_tab(): void
    {
        $settings = $this->settings->get_settings();
        echo '<form method="post">';
        wp_nonce_field('bressol_b2b_settings_save');
        echo '<table class="form-table"><tbody>';
        echo $this->render_text_row('Token TTL (días)', 'token_ttl_days', (string) ($settings['token_ttl_days'] ?? 30), true);
        echo $this->render_select_row('Sales owner 1', 'sales_owner_user_id_1', (string) ($settings['sales_owner_user_id_1'] ?? ''), $this->get_user_options());
        echo $this->render_select_row('Sales owner 2', 'sales_owner_user_id_2', (string) ($settings['sales_owner_user_id_2'] ?? ''), $this->get_user_options());
        echo $this->render_page_row('Signup page', 'signup_page_id', (int) ($settings['signup_page_id'] ?? 0));
        echo $this->render_textarea_row('Tiers (una línea cada uno)', 'tier_options', implode("\n", $this->settings->get_tier_options()), false);
        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">Guardar ajustes</button></p>';
        echo '</form>';
    }

    private function handle_tasks_actions(): void
    {
        if (!$this->current_user_can()) {
            return;
        }
        if (!isset($_POST['b2b_task_action'])) {
            return;
        }
        if (!wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'bressol_b2b_task_action')) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_POST['b2b_task_action']));
        $taskId = isset($_POST['task_id']) ? absint($_POST['task_id']) : 0;
        if ($taskId <= 0) {
            return;
        }
        if ($action === 'mark_done') {
            if ($this->taskService->mark_done($taskId)) {
                add_settings_error('bressol_b2b', 'task_done', 'Tarea completada.', 'updated');
            } else {
                add_settings_error('bressol_b2b', 'task_done_fail', 'No se pudo completar la tarea.', 'error');
            }
            return;
        }
        if ($action === 'snooze_24h') {
            if ($this->taskService->snooze($taskId, 24)) {
                add_settings_error('bressol_b2b', 'task_snoozed', 'Tarea pospuesta 24h.', 'updated');
            } else {
                add_settings_error('bressol_b2b', 'task_snoozed_fail', 'No se pudo posponer la tarea.', 'error');
            }
            return;
        }
        if ($action === 'update_note') {
            $note = isset($_POST['task_note']) ? (string) wp_unslash($_POST['task_note']) : '';
            if ($this->taskService->update_note($taskId, $note)) {
                add_settings_error('bressol_b2b', 'task_note', 'Nota guardada.', 'updated');
            } else {
                add_settings_error('bressol_b2b', 'task_note_fail', 'No se pudo guardar la nota.', 'error');
            }
        }
    }

    private function handle_settings_save(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }
        check_admin_referer('bressol_b2b_settings_save');

        $tierLines = isset($_POST['tier_options']) ? explode("\n", (string) wp_unslash($_POST['tier_options'])) : [];
        $tiers = [];
        foreach ($tierLines as $line) {
            $tier = sanitize_key(trim($line));
            if ($tier !== '') {
                $tiers[] = $tier;
            }
        }

        $this->settings->update([
            'token_ttl_days' => absint($_POST['token_ttl_days'] ?? 30),
            'sales_owner_user_id_1' => absint($_POST['sales_owner_user_id_1'] ?? 0),
            'sales_owner_user_id_2' => absint($_POST['sales_owner_user_id_2'] ?? 0),
            'signup_page_id' => absint($_POST['signup_page_id'] ?? 0),
            'tier_options' => $tiers,
        ]);

        add_settings_error('bressol_b2b', 'settings_saved', 'Ajustes guardados.', 'updated');
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function get_lead_payload_from_request(array $input): array
    {
        return [
            'email' => isset($input['email']) ? sanitize_email((string) wp_unslash($input['email'])) : '',
            'company_name' => isset($input['company_name']) ? sanitize_text_field((string) wp_unslash($input['company_name'])) : '',
            'contact_name' => isset($input['contact_name']) ? sanitize_text_field((string) wp_unslash($input['contact_name'])) : '',
            'city' => isset($input['city']) ? sanitize_text_field((string) wp_unslash($input['city'])) : '',
            'phone' => isset($input['phone']) ? sanitize_text_field((string) wp_unslash($input['phone'])) : '',
            'business_type' => isset($input['business_type']) ? sanitize_key((string) wp_unslash($input['business_type'])) : '',
            'tier' => isset($input['tier']) ? sanitize_key((string) wp_unslash($input['tier'])) : '',
            'status' => isset($input['status']) ? sanitize_key((string) wp_unslash($input['status'])) : '',
            'contact_basis' => isset($input['contact_basis']) ? sanitize_key((string) wp_unslash($input['contact_basis'])) : '',
            'sales_stage' => isset($input['sales_stage']) ? sanitize_key((string) wp_unslash($input['sales_stage'])) : '',
            'next_followup_at' => isset($input['next_followup_at']) ? sanitize_text_field((string) wp_unslash($input['next_followup_at'])) : '',
            'source' => isset($input['source']) ? sanitize_key((string) wp_unslash($input['source'])) : '',
            'source_ref_event_id' => isset($input['source_ref_event_id']) ? absint($input['source_ref_event_id']) : null,
            'interests_json' => isset($input['interests_json']) ? sanitize_textarea_field((string) wp_unslash($input['interests_json'])) : '',
            'owner_user_id' => isset($input['owner_user_id']) ? absint($input['owner_user_id']) : 0,
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array{type:string,note:string,assigned_user_id:int,due_at:string}
     */
    private function get_task_payload_from_request(array $input): array
    {
        $due = isset($input['task_due_at']) ? sanitize_text_field((string) wp_unslash($input['task_due_at'])) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $due)) {
            $due = current_time('mysql');
        }

        return [
            'type' => isset($input['task_type']) ? sanitize_key((string) wp_unslash($input['task_type'])) : '',
            'note' => isset($input['task_note']) ? substr(sanitize_text_field((string) wp_unslash($input['task_note'])), 0, 200) : '',
            'assigned_user_id' => isset($input['task_assigned_user_id']) ? absint($input['task_assigned_user_id']) : 0,
            'due_at' => $due,
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function get_leads_filters(array $input): array
    {
        return [
            'status' => isset($input['status']) ? sanitize_key((string) wp_unslash($input['status'])) : '',
            'tier' => isset($input['tier']) ? sanitize_key((string) wp_unslash($input['tier'])) : '',
            'source' => isset($input['source']) ? sanitize_key((string) wp_unslash($input['source'])) : '',
            'owner_user_id' => isset($input['owner_user_id']) ? absint($input['owner_user_id']) : 0,
            'search' => isset($input['s']) ? sanitize_text_field((string) wp_unslash($input['s'])) : '',
            'temperature' => isset($input['temperature']) ? strtoupper(sanitize_key((string) wp_unslash($input['temperature']))) : '',
            'stale_only' => isset($input['stale_only']) ? absint($input['stale_only']) : 0,
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function get_tasks_filters(array $input): array
    {
        $filters = [
            'status' => isset($input['status']) ? sanitize_key((string) wp_unslash($input['status'])) : '',
            'type' => isset($input['type']) ? sanitize_key((string) wp_unslash($input['type'])) : '',
            'assigned_user_id' => isset($input['assigned_user_id']) ? absint($input['assigned_user_id']) : 0,
            'due_from' => isset($input['due_from']) ? sanitize_text_field((string) wp_unslash($input['due_from'])) : '',
            'due_to' => isset($input['due_to']) ? sanitize_text_field((string) wp_unslash($input['due_to'])) : '',
            'hot_only' => isset($input['hot_only']) ? absint($input['hot_only']) : 0,
            'overdue' => isset($input['overdue']) ? absint($input['overdue']) : 0,
            'due_today' => isset($input['due_today']) ? absint($input['due_today']) : 0,
            'assigned_to_me' => isset($input['assigned_to_me']) ? absint($input['assigned_to_me']) : 0,
        ];
        if (!empty($filters['assigned_to_me'])) {
            $filters['assigned_user_id'] = get_current_user_id();
        }
        return $filters;
    }

    /** @param array<string, mixed> $filters */
    private function render_leads_filters_form(array $filters): string
    {
        $html = '<form method="get" style="margin:12px 0;">';
        $html .= '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';
        $html .= '<input type="hidden" name="tab" value="leads" />';
        $html .= '<label>Buscar <input type="search" name="s" value="' . esc_attr((string) $filters['search']) . '" /></label> ';
        $html .= '<label>Status ' . $this->render_select('status', (string) $filters['status'], $this->status_options(true)) . '</label> ';
        $html .= '<label>Tier ' . $this->render_select('tier', (string) $filters['tier'], $this->get_tier_options(true)) . '</label> ';
        $html .= '<label>Temperatura ' . $this->render_select('temperature', (string) $filters['temperature'], ['' => 'Todas', 'COLD' => 'COLD', 'WARM' => 'WARM', 'HOT' => 'HOT']) . '</label> ';
        $checked = !empty($filters['stale_only']) ? 'checked' : '';
        $html .= '<label><input type="checkbox" name="stale_only" value="1" ' . $checked . ' /> Stale only</label> ';
        $html .= '<label>Fuente <input type="text" name="source" value="' . esc_attr((string) $filters['source']) . '" /></label> ';
        $html .= '<label>Owner ' . $this->render_select('owner_user_id', (string) $filters['owner_user_id'], $this->get_user_options(true)) . '</label> ';
        $html .= '<button class="button">Filtrar</button>';
        $html .= '</form>';
        return $html;
    }

    /** @param array<string, mixed> $filters */
    private function render_tasks_filters_form(array $filters): string
    {
        $html = '<form method="get" style="margin:12px 0;">';
        $html .= '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';
        $html .= '<input type="hidden" name="tab" value="tasks" />';
        $html .= '<label>Status ' . $this->render_select('status', (string) $filters['status'], ['' => 'Todos', 'open' => 'open', 'done' => 'done']) . '</label> ';
        $html .= '<label>Tipo <input type="text" name="type" value="' . esc_attr((string) ($filters['type'] ?? '')) . '" /></label> ';
        $html .= '<label>Asignado ' . $this->render_select('assigned_user_id', (string) $filters['assigned_user_id'], $this->get_user_options(true)) . '</label> ';
        $html .= '<label>Desde <input type="date" name="due_from" value="' . esc_attr((string) $filters['due_from']) . '" /></label> ';
        $html .= '<label>Hasta <input type="date" name="due_to" value="' . esc_attr((string) $filters['due_to']) . '" /></label> ';
        $html .= '<label><input type="checkbox" name="hot_only" value="1" ' . (!empty($filters['hot_only']) ? 'checked' : '') . ' /> HOT only</label> ';
        $html .= '<label><input type="checkbox" name="overdue" value="1" ' . (!empty($filters['overdue']) ? 'checked' : '') . ' /> Overdue</label> ';
        $html .= '<label><input type="checkbox" name="due_today" value="1" ' . (!empty($filters['due_today']) ? 'checked' : '') . ' /> Due today</label> ';
        $html .= '<label><input type="checkbox" name="assigned_to_me" value="1" ' . (!empty($filters['assigned_to_me']) ? 'checked' : '') . ' /> Assigned to me</label> ';
        $html .= '<button class="button">Filtrar</button>';
        $html .= '</form>';
        return $html;
    }

    private function render_tabs(string $active): string
    {
        $tabs = [
            'leads' => 'Leads',
            'tasks' => 'Tasks',
            'settings' => 'Settings',
        ];
        $html = '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $label) {
            $url = add_query_arg(['page' => self::PAGE_SLUG, 'tab' => $tab], admin_url('admin.php'));
            $class = $active === $tab ? 'nav-tab nav-tab-active' : 'nav-tab';
            $html .= '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        $html .= '</h2>';
        return $html;
    }

    private function render_diagnostics_box(): void
    {
        if (!current_user_can('administrator')) {
            return;
        }

        $diagnostics = $this->build_diagnostics_payload();
        $json = wp_json_encode($diagnostics, JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            $json = '{}';
        }
        $textId = 'b2b-diagnostics-json';

        echo '<div class="notice notice-info" style="padding:12px 16px;">';
        echo '<strong>Diagnostics</strong>';
        echo '<p><textarea id="' . esc_attr($textId) . '" readonly rows="14" style="width:100%;font-family:monospace;">'
            . esc_textarea($json) . '</textarea></p>';
        echo '<p><button type="button" class="button button-small" data-b2b-copy="' . esc_attr($textId) . '">Copy</button></p>';
        echo $this->render_copy_script();
        echo '<div style="margin-top:12px;">';
        echo $this->render_diagnostics_actions_form();
        echo '</div>';
        if ($this->selfTestResult !== null) {
            echo '<h4>Self-test result</h4>';
            echo '<pre style="white-space:pre-wrap;background:#fff;padding:8px;border:1px solid #ccd0d4;">'
                . esc_html(wp_json_encode($this->selfTestResult, JSON_PRETTY_PRINT) ?: '') . '</pre>';
        }
        echo '</div>';
    }

    private function render_diagnostics_actions_form(): string
    {
        $page = self::PAGE_SLUG;
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'leads';
        if (!in_array($tab, ['leads', 'tasks', 'settings'], true)) {
            $tab = 'leads';
        }

        $html = '<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
        $html .= wp_nonce_field('bressol_b2b_diagnostics', '_wpnonce', true, false);
        $html .= '<input type="hidden" name="page" value="' . esc_attr($page) . '" />';
        $html .= '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />';
        $html .= '<button type="submit" class="button" name="b2b_diag_action" value="test_wp_mail">Send test wp_mail</button>';
        $html .= '<button type="submit" class="button" name="b2b_diag_action" value="process_esp_queue">Process ESP queue now</button>';
        $html .= '<button type="submit" class="button" name="b2b_diag_action" value="requeue_consent_missing">Requeue consent_missing jobs</button>';
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $html .= '<button type="submit" class="button button-primary" name="b2b_diag_action" value="b2b_e2e_self_test">Run B2B E2E self-test (dev)</button>';
        }
        $html .= '</form>';
        return $html;
    }

    private function handle_diagnostics_actions(string $tab): void
    {
        if (!current_user_can('administrator')) {
            return;
        }
        if (!isset($_POST['b2b_diag_action'])) {
            return;
        }
        if (!wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'bressol_b2b_diagnostics')) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_POST['b2b_diag_action']));
        if ($action === 'test_wp_mail') {
            $this->handle_wp_mail_test();
            return;
        }
        if ($action === 'process_esp_queue') {
            $this->handle_esp_queue_process();
            return;
        }
        if ($action === 'requeue_consent_missing') {
            $this->handle_requeue_consent_missing();
            return;
        }
        if ($action === 'b2b_e2e_self_test') {
            $this->handle_b2b_e2e_self_test();
        }
    }

    private function handle_wp_mail_test(): void
    {
        $adminEmail = (string) get_option('admin_email');
        $errorMessage = '';
        $listener = static function ($wpError) use (&$errorMessage): void {
            if ($wpError instanceof \WP_Error) {
                $errorMessage = $wpError->get_error_message();
                return;
            }
            $errorMessage = 'wp_mail_failed';
        };
        add_action('wp_mail_failed', $listener);
        $sent = false;
        try {
            $sent = (bool) wp_mail($adminEmail, '[B2B] wp_mail test', 'Hello from B2B diagnostics');
        } finally {
            remove_action('wp_mail_failed', $listener);
        }

        if ($sent) {
            add_settings_error('bressol_b2b', 'b2b_mail_ok', 'wp_mail enviado correctamente.', 'updated');
            return;
        }

        $message = $errorMessage !== '' ? $this->truncate_error($errorMessage) : 'wp_mail_failed';
        add_settings_error('bressol_b2b', 'b2b_mail_fail', 'wp_mail falló: ' . esc_html($message), 'error');
    }

    private function handle_esp_queue_process(): void
    {
        $stats = (new SenderService())->run_once(20);
        $processed = (int) ($stats['processed'] ?? 0);
        $sent = (int) ($stats['sent'] ?? 0);
        $failed = (int) ($stats['failed'] ?? 0);

        $summary = 'ESP procesado. processed=' . $processed . ', sent=' . $sent . ', failed=' . $failed . '.';
        $errors = isset($stats['errors']) && is_array($stats['errors']) ? $stats['errors'] : [];
        $errors = array_values(array_unique(array_filter(array_map([$this, 'sanitize_error'], $errors))));
        if ($errors !== []) {
            $summary .= ' last_errors: ' . implode(' | ', array_slice($errors, 0, 3));
        }
        add_settings_error('bressol_b2b', 'b2b_esp_queue', $summary, $failed > 0 ? 'error' : 'updated');

        $results = isset($stats['results']) && is_array($stats['results']) ? $stats['results'] : [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }
            $email = isset($result['email']) ? (string) $result['email'] : '';
            $status = isset($result['status']) ? (string) $result['status'] : '';
            if ($email === '' || $status === '') {
                continue;
            }
            $lead = $this->leads->find_by_email_lower(strtolower($email));
            if (!$lead || empty($lead['id'])) {
                continue;
            }
            $leadId = (int) $lead['id'];
            if ($status === 'sent') {
                $this->events->insert_event($leadId, 'esp_email_sent', ['source' => 'esp_manual']);
            } else {
                $this->events->insert_event($leadId, 'esp_email_failed', ['source' => 'esp_manual']);
            }
        }
    }

    private function handle_requeue_consent_missing(): void
    {
        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';
        $jobsTable = $wpdb->prefix . 'bressol_esp_jobs';
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';

        $items = $wpdb->get_results(
            "SELECT q.id, q.email
             FROM {$queueTable} q
             INNER JOIN {$jobsTable} j ON q.job_id = j.id
             INNER JOIN {$campaignsTable} c ON j.campaign_id = c.id
             WHERE q.status = 'skipped'
               AND q.last_error = 'consent_missing'
               AND c.name LIKE 'B2B %'
             ORDER BY q.id DESC
             LIMIT 50",
            ARRAY_A
        );

        if (!$items) {
            add_settings_error('bressol_b2b', 'b2b_requeue_none', 'No hay jobs para reencolar.', 'updated');
            return;
        }

        $leadService = new B2BLeadService();
        $requeued = 0;
        foreach ($items as $item) {
            $email = (string) ($item['email'] ?? '');
            if ($email === '') {
                continue;
            }
            $lead = $this->leads->find_by_email_lower(strtolower($email));
            if ($lead) {
                $leadService->sync_marketing_consent($lead, 'b2b_requeue');
            }
            $wpdb->update(
                $queueTable,
                [
                    'status' => 'pending',
                    'attempts' => 0,
                    'last_error' => null,
                    'locked_at' => null,
                ],
                ['id' => (int) ($item['id'] ?? 0)],
                ['%s', '%d', '%s', '%s'],
                ['%d']
            );
            $requeued++;
        }

        add_settings_error('bressol_b2b', 'b2b_requeue_done', 'Reencolados: ' . (int) $requeued, 'updated');
    }

    private function handle_b2b_e2e_self_test(): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            add_settings_error('bressol_b2b', 'b2b_self_test_blocked', 'Self-test solo en entorno de desarrollo.', 'error');
            return;
        }

        $baseEmail = (string) get_option('admin_email');
        $timestamp = (string) current_time('timestamp');
        $email = $this->with_plus_tag($baseEmail, 'test' . $timestamp);

        $leadService = new B2BLeadService();
        $result = $leadService->register_signup([
            'email' => $email,
            'business_type' => 'other',
        ]);

        $leadId = (int) ($result['lead_id'] ?? 0);
        $lead = $leadId > 0 ? $this->leads->find_by_id($leadId) : null;
        $token = $lead ? (string) ($lead['consent_token'] ?? '') : '';

        $senderStats = (new SenderService())->run_once(20);
        $queueStatus = $this->find_queue_status_for_email($email);

        $catalogUrl = $token !== '' ? add_query_arg(['token' => $token], home_url('/b2b/catalog')) : '';
        $pricelistUrl = $token !== '' ? add_query_arg(['token' => $token], home_url('/b2b/pricelist')) : '';

        $this->selfTestResult = [
            'lead_id' => $leadId,
            'token_created_bool' => $token !== '',
            'email_enqueued_bool' => $leadId > 0,
            'sender_processed' => (int) ($senderStats['processed'] ?? 0),
            'sender_sent' => (int) ($senderStats['sent'] ?? 0),
            'sender_failed' => (int) ($senderStats['failed'] ?? 0),
            'final_queue_status_for_that_email' => $queueStatus,
            'catalog_url' => $catalogUrl,
            'pricelist_url' => $pricelistUrl,
        ];
    }

    private function build_diagnostics_payload(): array
    {
        global $wpdb;
        $leadCount = $this->leads->count_by_filters([]);
        $taskCount = $this->tasks->count_by_filters([]);
        $recentLeads = $this->leads->find_by_filters([], 5, 1);

        $leadIds = [];
        foreach ($recentLeads as $lead) {
            $leadIds[] = (int) ($lead['id'] ?? 0);
        }
        $events = new LeadEventsRepository();
        $leadService = new B2BLeadService($this->leads, $events);
        $lastEvents = $events->get_last_event_types($leadIds);

        $latestLeads = [];
        foreach ($recentLeads as $lead) {
            $leadId = (int) ($lead['id'] ?? 0);
            $lastEvent = $leadId > 0 && isset($lastEvents[$leadId]) ? $lastEvents[$leadId] : '';
            $temp = $leadService->compute_temperature(array_merge($lead, ['last_event_type' => $lastEvent]));
            $latestLeads[] = [
                'id' => $leadId,
                'status' => (string) ($lead['status'] ?? ''),
                'email_masked' => Masking::mask_email((string) ($lead['email'] ?? '')),
                'consented_at' => (string) ($lead['consented_at'] ?? ''),
                'last_activity_at' => (string) ($lead['last_activity_at'] ?? ''),
                'lead_score' => (int) ($lead['lead_score'] ?? 0),
                'temperature' => (string) ($temp['temperature'] ?? ''),
                'stale' => !empty($temp['stale']),
                'last_event_type' => $lastEvent,
            ];
        }

        $consentBridge = [];
        foreach ($recentLeads as $lead) {
            $email = (string) ($lead['email'] ?? '');
            $state = class_exists(CustomerService::class)
                ? (new CustomerService())->get_effective_marketing_state($email)
                : [];
            $consentBridge[] = [
                'lead_id' => (int) ($lead['id'] ?? 0),
                'b2b_consented_at_exists' => !empty($lead['consented_at']),
                'esp_consent_exists_bool' => (bool) ($state['effective_flags']['can_receive_marketing'] ?? false),
            ];
        }

        $espQueueStats = $this->get_b2b_esp_queue_stats();
        $lastErrors = $this->get_b2b_esp_last_errors();
        $temperatureStats = $this->leads->count_temperature_stats();
        $tasksStats = $this->build_tasks_stats();
        $topHotLeads = $this->build_top_hot_leads();

        $uploads = wp_upload_dir();
        $baseDir = (string) ($uploads['basedir'] ?? '');
        [$catalogPath, $catalogFound] = $this->resolve_pdf_file($baseDir, 'catalog');
        [$pricelistPath, $pricelistFound] = $this->resolve_pdf_file($baseDir, 'pricelist');

        $rewriteRules = get_option('rewrite_rules', []);
        $rewritesOk = is_array($rewriteRules)
            && array_key_exists('^b2b/catalog/?$', $rewriteRules)
            && array_key_exists('^b2b/pricelist/?$', $rewriteRules);
        $fallbackOk = $this->fallback_ok();

        $nextReminder = wp_next_scheduled(ReminderService::CRON_HOOK);

        return [
            'totals' => [
                'leads_count' => (int) $leadCount,
                'tasks_count' => (int) $taskCount,
            ],
            'latest_leads' => $latestLeads,
            'temperature_stats' => [
                'HOT' => (int) ($temperatureStats['HOT'] ?? 0),
                'WARM' => (int) ($temperatureStats['WARM'] ?? 0),
                'COLD' => (int) ($temperatureStats['COLD'] ?? 0),
                'stale_count' => (int) ($temperatureStats['stale'] ?? 0),
            ],
            'tasks_stats' => $tasksStats,
            'top_hot_leads' => $topHotLeads,
            'esp_queue_stats' => $espQueueStats,
            'consent_bridge_stats' => $consentBridge,
            'endpoints_health' => [
                'rewrites_ok_bool' => $rewritesOk,
                'fallback_ok_bool' => $fallbackOk,
                'catalog_pdf_found_bool' => $catalogFound,
                'pricelist_pdf_found_bool' => $pricelistFound,
                'effective_catalog_path' => $catalogPath !== '' ? basename($catalogPath) : '',
                'effective_pricelist_path' => $pricelistPath !== '' ? basename($pricelistPath) : '',
            ],
            'cron_health' => [
                'next_b2b_reminder_timestamp' => $nextReminder ? (int) $nextReminder : null,
            ],
            'last_errors' => $lastErrors,
        ];
    }

    /** @return array<string, int> */
    private function build_tasks_stats(): array
    {
        $overdue = $this->tasks->count_by_filters([
            'overdue' => 1,
            'status' => 'open',
        ]);
        $dueToday = $this->tasks->count_by_filters([
            'due_today' => 1,
            'status' => 'open',
        ]);
        $hotOpen = $this->tasks->count_by_filters([
            'hot_only' => 1,
            'status' => 'open',
        ]);
        $assignedToMe = $this->tasks->count_by_filters([
            'assigned_user_id' => get_current_user_id(),
            'status' => 'open',
        ]);

        return [
            'overdue_count' => (int) $overdue,
            'due_today_count' => (int) $dueToday,
            'hot_open_count' => (int) $hotOpen,
            'assigned_to_me_count' => (int) $assignedToMe,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function build_top_hot_leads(): array
    {
        $leads = $this->leads->find_hot_leads(5);
        if ($leads === []) {
            return [];
        }
        $leadIds = [];
        foreach ($leads as $lead) {
            $leadIds[] = (int) ($lead['id'] ?? 0);
        }
        $events = new LeadEventsRepository();
        $leadService = new B2BLeadService($this->leads, $events);
        $lastEvents = $events->get_last_event_types($leadIds);

        $output = [];
        foreach ($leads as $lead) {
            $leadId = (int) ($lead['id'] ?? 0);
            $temp = $leadService->compute_temperature(array_merge($lead, [
                'last_event_type' => $lastEvents[$leadId] ?? '',
            ]));
            $output[] = [
                'lead_id' => $leadId,
                'temperature' => (string) ($temp['temperature'] ?? ''),
                'owner_user_id' => (int) ($lead['owner_user_id'] ?? 0),
                'next_followup_at' => (string) ($lead['next_followup_at'] ?? ''),
                'email_masked' => Masking::mask_email((string) ($lead['email'] ?? '')),
            ];
        }

        return $output;
    }

    private function get_b2b_esp_queue_stats(): array
    {
        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';
        $jobsTable = $wpdb->prefix . 'bressol_esp_jobs';
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';

        $rows = $wpdb->get_results(
            "SELECT q.status
             FROM {$queueTable} q
             INNER JOIN {$jobsTable} j ON q.job_id = j.id
             INNER JOIN {$campaignsTable} c ON j.campaign_id = c.id
             WHERE c.name LIKE 'B2B %'
             ORDER BY q.id DESC
             LIMIT 50",
            ARRAY_A
        );

        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'queued' => 0, 'pending' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (!isset($stats[$status])) {
                $stats[$status] = 0;
            }
            $stats[$status]++;
        }

        return $stats;
    }

    private function get_b2b_esp_last_errors(): array
    {
        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';
        $jobsTable = $wpdb->prefix . 'bressol_esp_jobs';
        $campaignsTable = $wpdb->prefix . 'bressol_esp_campaigns';

        $rows = $wpdb->get_results(
            "SELECT q.last_error
             FROM {$queueTable} q
             INNER JOIN {$jobsTable} j ON q.job_id = j.id
             INNER JOIN {$campaignsTable} c ON j.campaign_id = c.id
             WHERE c.name LIKE 'B2B %' AND q.last_error IS NOT NULL AND q.last_error <> ''
             ORDER BY q.id DESC
             LIMIT 5",
            ARRAY_A
        );

        $errors = [];
        foreach ($rows as $row) {
            $errors[] = $this->sanitize_error((string) ($row['last_error'] ?? ''));
        }

        return array_values(array_filter($errors));
    }

    private function render_copy_script(): string
    {
        return '<script>
            document.addEventListener("click", function(e) {
                var btn = e.target.closest("[data-b2b-copy]");
                if (!btn) return;
                var targetId = btn.getAttribute("data-b2b-copy");
                var node = targetId ? document.getElementById(targetId) : null;
                if (!node) return;
                var text = node.value || node.textContent || "";
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text);
                    return;
                }
                var input = document.createElement("input");
                input.value = text;
                document.body.appendChild(input);
                input.select();
                try { document.execCommand("copy"); } catch (e) {}
                document.body.removeChild(input);
            }, {capture: true});
        </script>';
    }

    private function sanitize_error(string $message): string
    {
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message ?? '');
        $message = trim((string) $message);
        if ($message === '') {
            return '';
        }
        if (strlen($message) <= 160) {
            return $message;
        }
        return substr($message, 0, 160) . '…';
    }

    private function with_plus_tag(string $email, string $tag): string
    {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return 'ivan+' . $tag . '@bressol.nl';
        }
        [$local, $domain] = explode('@', $email, 2);
        if (strpos($local, '+') !== false) {
            $local = substr($local, 0, strpos($local, '+'));
        }
        return $local . '+' . $tag . '@' . $domain;
    }

    private function find_queue_status_for_email(string $email): string
    {
        global $wpdb;
        $queueTable = $wpdb->prefix . 'bressol_esp_queue_items';
        $status = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status FROM {$queueTable} WHERE email = %s ORDER BY id DESC LIMIT 1",
                $email
            )
        );
        return $status !== '' ? $status : 'unknown';
    }
    /** @param array<string, string> $options */
    private function render_text_row(string $label, string $name, string $value, bool $required): string
    {
        $req = $required ? 'required' : '';
        return '<tr><th>' . esc_html($label) . '</th><td><input type="text" name="' . esc_attr($name)
            . '" value="' . esc_attr($value) . '" ' . $req . ' class="regular-text" /></td></tr>';
    }

    /** @param array<string, string> $options */
    private function render_select_row(string $label, string $name, string $value, array $options): string
    {
        return '<tr><th>' . esc_html($label) . '</th><td>' . $this->render_select($name, $value, $options) . '</td></tr>';
    }

    private function render_textarea_row(string $label, string $name, string $value, bool $required): string
    {
        $req = $required ? 'required' : '';
        return '<tr><th>' . esc_html($label) . '</th><td><textarea name="' . esc_attr($name)
            . '" rows="4" cols="50" ' . $req . '>' . esc_textarea($value) . '</textarea></td></tr>';
    }

    private function render_page_row(string $label, string $name, int $selected): string
    {
        $dropdown = wp_dropdown_pages([
            'name' => $name,
            'selected' => $selected,
            'show_option_none' => '-',
            'echo' => 0,
        ]);
        return '<tr><th>' . esc_html($label) . '</th><td>' . $dropdown . '</td></tr>';
    }

    /** @param array<string, string> $options */
    private function render_select(string $name, string $value, array $options): string
    {
        $html = '<select name="' . esc_attr($name) . '">';
        foreach ($options as $key => $label) {
            $html .= '<option value="' . esc_attr((string) $key) . '" ' . selected((string) $value, (string) $key, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /** @return array<string, string> */
    private function get_user_options(bool $includeEmpty = false): array
    {
        $users = get_users(['fields' => ['ID', 'display_name']]);
        $options = $includeEmpty ? ['' => 'Todos'] : [];
        foreach ($users as $user) {
            $label = $user->display_name !== '' ? (string) $user->display_name : (string) $user->ID;
            $options[(string) $user->ID] = $label;
        }
        return $options;
    }

    /** @return array<string, string> */
    private function status_options(bool $includeAll = false): array
    {
        $options = [
            'NEW' => 'NEW',
            'NEEDS_CONSENT' => 'NEEDS_CONSENT',
            'CONSENTED' => 'CONSENTED',
            'ENGAGED' => 'ENGAGED',
            'SQL' => 'SQL',
            'WON' => 'WON',
            'LOST' => 'LOST',
        ];
        return $includeAll ? ['' => 'Todos'] + $options : $options;
    }

    /** @return array<string, string> */
    private function contact_basis_options(): array
    {
        return [
            'no_consent' => 'no_consent',
            'consent_explicit' => 'consent_explicit',
            'relationship_1to1_followup' => 'relationship_1to1_followup',
        ];
    }

    /** @return array<string, string> */
    private function business_type_options(): array
    {
        return [
            '' => '-',
            'gourmet' => 'gourmet',
            'horeca' => 'horeca',
            'corporate' => 'corporate',
            'other' => 'other',
        ];
    }

    /** @return array<string, string> */
    private function sales_stage_options(): array
    {
        return [
            'new' => 'new',
            'contacted' => 'contacted',
            'sample_sent' => 'sample_sent',
            'negotiation' => 'negotiation',
            'won' => 'won',
            'lost' => 'lost',
        ];
    }

    /** @return array<string, string> */
    private function get_tier_options(bool $includeAll = false): array
    {
        $tiers = $this->settings->get_tier_options();
        $options = [];
        if ($includeAll) {
            $options[''] = 'Todos';
        }
        foreach ($tiers as $tier) {
            $options[$tier] = $tier;
        }
        return $options;
    }

    private function default_tier(): string
    {
        $tiers = $this->settings->get_tier_options();
        return $tiers[0] ?? 'other';
    }

    private function user_label(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        $user = get_userdata($userId);
        if (!$user) {
            return '';
        }
        return $user->display_name !== '' ? (string) $user->display_name : (string) $userId;
    }

    private function render_temperature_badge(string $temperature, bool $stale): string
    {
        if ($temperature === '') {
            return '';
        }
        $color = '#6c757d';
        if ($temperature === 'HOT') {
            $color = '#c92a2a';
        } elseif ($temperature === 'WARM') {
            $color = '#f08c00';
        } elseif ($temperature === 'COLD') {
            $color = '#1c7ed6';
        }
        $html = '<span style="display:inline-block;padding:2px 6px;border-radius:10px;font-size:11px;font-weight:600;';
        $html .= 'background:' . esc_attr($color) . ';color:#fff;">' . esc_html($temperature) . '</span>';
        if ($stale) {
            $html .= ' <span style="font-size:11px;color:#555;">stale</span>';
        }
        return $html;
    }

    private function capability(): string
    {
        return Capabilities::CAP;
    }

    private function current_user_can(): bool
    {
        return current_user_can($this->capability()) || current_user_can('manage_options');
    }

    private function build_public_url(string $page, string $token): string
    {
        $base = home_url('/b2b/' . $page);
        return add_query_arg(['token' => $token], $base);
    }

    /** @return array{0:string,1:bool} */
    private function resolve_pdf_file(string $baseDir, string $slug): array
    {
        if ($baseDir === '') {
            return ['', false];
        }
        $dir = rtrim($baseDir, '/') . '/b2b';
        $pdfPath = $dir . '/' . $slug . '.pdf';
        if (file_exists($pdfPath)) {
            return [$pdfPath, true];
        }
        $plainPath = $dir . '/' . $slug;
        if (file_exists($plainPath)) {
            return [$plainPath, true];
        }
        return ['', false];
    }

    private function fallback_ok(): bool
    {
        global $wp;
        if (!isset($wp) || !isset($wp->public_query_vars) || !is_array($wp->public_query_vars)) {
            return true;
        }
        return in_array('b2b_doc', $wp->public_query_vars, true);
    }

    private function sanitize_timeline_context(string $context): string
    {
        if ($context === '') {
            return '';
        }
        $decoded = json_decode($context, true);
        if (is_array($decoded)) {
            $decoded = $this->mask_context_array($decoded);
            return (string) wp_json_encode($decoded);
        }
        return $this->mask_emails_in_text($context);
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    private function mask_context_array(array $context): array
    {
        foreach ($context as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (stripos($key, 'email') !== false) {
                $context[$key] = Masking::mask_email($value);
                continue;
            }
            $context[$key] = $this->mask_emails_in_text($value);
        }
        return $context;
    }

    private function mask_emails_in_text(string $text): string
    {
        return preg_replace_callback(
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            function (array $matches): string {
                return Masking::mask_email($matches[0] ?? '');
            },
            $text
        ) ?? $text;
    }
}
