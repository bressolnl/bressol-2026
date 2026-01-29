<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Admin;

use Bressol\Modules\B2B\Repositories\LeadEventsRepository;
use Bressol\Modules\B2B\Repositories\LeadRepository;
use Bressol\Modules\B2B\Repositories\TaskRepository;
use Bressol\Modules\B2B\Services\Capabilities;
use Bressol\Modules\B2B\Services\LeadService;
use Bressol\Modules\B2B\Services\Settings;
use Bressol\Modules\B2B\Services\TaskService;

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

        if ($tab === 'tasks') {
            $this->handle_tasks_actions();
        }

        if ($tab === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_settings_save();
        }

        echo '<div class="wrap">';
        echo '<h1>B2B</h1>';
        settings_errors('bressol_b2b');
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
        echo $this->render_text_row('Teléfono', 'phone', (string) ($lead['phone'] ?? ''), false);
        echo $this->render_select_row('Business type', 'business_type', (string) ($lead['business_type'] ?? ''), $this->business_type_options());
        echo $this->render_select_row('Tier', 'tier', (string) ($lead['tier'] ?? ''), $this->get_tier_options());
        echo $this->render_select_row('Estado', 'status', (string) ($lead['status'] ?? ''), $this->status_options());
        echo $this->render_select_row('Contact basis', 'contact_basis', (string) ($lead['contact_basis'] ?? ''), $this->contact_basis_options());
        echo $this->render_text_row('Fuente', 'source', (string) ($lead['source'] ?? ''), false);
        echo $this->render_text_row('Evento (MarketsEvents ID)', 'source_ref_event_id', (string) ($lead['source_ref_event_id'] ?? ''), false);
        echo $this->render_textarea_row('Intereses (JSON)', 'interests_json', (string) ($lead['interests_json'] ?? ''), false);
        echo $this->render_select_row('Owner', 'owner_user_id', (string) ($lead['owner_user_id'] ?? ''), $this->get_user_options());
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
        if (!isset($_GET['b2b_task_action'])) {
            return;
        }
        if (!wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), 'bressol_b2b_task_action')) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_GET['b2b_task_action']));
        $taskId = isset($_GET['task_id']) ? absint($_GET['task_id']) : 0;
        if ($action === 'mark_done' && $taskId > 0) {
            if ($this->taskService->mark_done($taskId)) {
                add_settings_error('bressol_b2b', 'task_done', 'Tarea completada.', 'updated');
            } else {
                add_settings_error('bressol_b2b', 'task_done_fail', 'No se pudo completar la tarea.', 'error');
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
            'phone' => isset($input['phone']) ? sanitize_text_field((string) wp_unslash($input['phone'])) : '',
            'business_type' => isset($input['business_type']) ? sanitize_key((string) wp_unslash($input['business_type'])) : '',
            'tier' => isset($input['tier']) ? sanitize_key((string) wp_unslash($input['tier'])) : '',
            'status' => isset($input['status']) ? sanitize_key((string) wp_unslash($input['status'])) : '',
            'contact_basis' => isset($input['contact_basis']) ? sanitize_key((string) wp_unslash($input['contact_basis'])) : '',
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
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function get_tasks_filters(array $input): array
    {
        return [
            'status' => isset($input['status']) ? sanitize_key((string) wp_unslash($input['status'])) : '',
            'assigned_user_id' => isset($input['assigned_user_id']) ? absint($input['assigned_user_id']) : 0,
            'due_from' => isset($input['due_from']) ? sanitize_text_field((string) wp_unslash($input['due_from'])) : '',
            'due_to' => isset($input['due_to']) ? sanitize_text_field((string) wp_unslash($input['due_to'])) : '',
        ];
    }

    /** @param array<string, mixed> $filters */
    private function render_leads_filters_form(array $filters): string
    {
        $html = '<form method="get" style="margin:12px 0;">';
        $html .= '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';
        $html .= '<input type="hidden" name="tab" value="leads" />';
        $html .= '<label>Status ' . $this->render_select('status', (string) $filters['status'], $this->status_options(true)) . '</label> ';
        $html .= '<label>Tier ' . $this->render_select('tier', (string) $filters['tier'], $this->get_tier_options(true)) . '</label> ';
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
        $html .= '<label>Asignado ' . $this->render_select('assigned_user_id', (string) $filters['assigned_user_id'], $this->get_user_options(true)) . '</label> ';
        $html .= '<label>Desde <input type="date" name="due_from" value="' . esc_attr((string) $filters['due_from']) . '" /></label> ';
        $html .= '<label>Hasta <input type="date" name="due_to" value="' . esc_attr((string) $filters['due_to']) . '" /></label> ';
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
                $context[$key] = $this->mask_email($value);
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
                return $this->mask_email($matches[0] ?? '');
            },
            $text
        ) ?? $text;
    }

    private function mask_email(string $email): string
    {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }
        [$local, $domain] = explode('@', $email, 2);
        $localMasked = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 2)) . substr($local, -1);
        $domainParts = explode('.', $domain);
        $domainMasked = substr($domainParts[0], 0, 1) . str_repeat('*', max(1, strlen($domainParts[0]) - 2)) . substr($domainParts[0], -1);
        $suffix = count($domainParts) > 1 ? '.' . end($domainParts) : '';
        return $localMasked . '@' . $domainMasked . $suffix;
    }
}
