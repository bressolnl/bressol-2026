<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Admin;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;
use Bressol\Modules\MarketsEvents\Repositories\EventDocumentsRepository;
use Bressol\Modules\MarketsEvents\Repositories\EventCostItemsRepository;
use Bressol\Modules\MarketsEvents\Services\Capabilities;
use Bressol\Modules\MarketsEvents\Services\EventService;
use Bressol\Modules\MarketsEvents\Services\PosEventContextService;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    private const PAGE_SLUG = 'bressol-markets-events';
    private const EDIT_SLUG = 'bressol-markets-events-edit';
    private const PER_PAGE = 20;

    private EventRepository $repository;
    private EventService $service;
    private Capabilities $capabilities;

    public function __construct(
        EventRepository $repository,
        EventService $service,
        Capabilities $capabilities
    ) {
        $this->repository = $repository;
        $this->service = $service;
        $this->capabilities = $capabilities;
    }

    public function registerMenus(): void
    {
        $capability = $this->get_capability();

        add_submenu_page(
            'bressol',
            'Markets & Events',
            'Markets & Events',
            $capability,
            self::PAGE_SLUG,
            [$this, 'renderListPage']
        );

        add_submenu_page(
            null,
            'Edit Event',
            'Edit Event',
            $capability,
            self::EDIT_SLUG,
            [$this, 'renderEditPage']
        );
    }

    public function renderListPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $this->hydrate_notice_from_query();
        $this->handle_list_actions();

        $filters = $this->get_filters_from_request($_GET);
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $limit = self::PER_PAGE;

        $events = $this->repository->find_by_filters($filters, $limit, $page);
        $total = $this->repository->count_by_filters($filters);
        $totalPages = (int) ceil($total / $limit);

        $createUrl = admin_url('admin.php?page=' . self::EDIT_SLUG);

        echo '<div class="wrap">';
        echo '<h1>Markets & Events</h1>';
        settings_errors('bressol_markets_events');

        echo '<p><a class="button button-primary" href="' . esc_url($createUrl) . '">Nuevo evento</a></p>';

        echo $this->render_filters_form($filters);

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">';
        wp_nonce_field('bressol_events_bulk_cancel', '_bressol_events_nonce');
        echo '<input type="hidden" name="bressol_events_action" value="bulk_cancel" />';
        // Preserve current filters/pagination across POST -> redirect
        echo '<input type="hidden" name="status" value="' . esc_attr((string) $filters['status']) . '" />';
        echo '<input type="hidden" name="type" value="' . esc_attr((string) $filters['type']) . '" />';
        echo '<input type="hidden" name="channel" value="' . esc_attr((string) $filters['channel']) . '" />';
        echo '<input type="hidden" name="date_from" value="' . esc_attr((string) $filters['date_from']) . '" />';
        echo '<input type="hidden" name="date_to" value="' . esc_attr((string) $filters['date_to']) . '" />';
        echo '<input type="hidden" name="paged" value="' . esc_attr((string) (isset($_GET['paged']) ? absint($_GET['paged']) : 1)) . '" />';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th><input type="checkbox" data-bressol-toggle-all /></th>';
        echo '<th>ID</th><th>Título</th><th>Tipo</th><th>Status</th><th>Inicio</th><th>Fin</th><th>Canal</th><th>Ubicación</th><th>Costes (€)</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';

        if ($events === []) {
            echo '<tr><td colspan="11">No hay eventos.</td></tr>';
        }

        foreach ($events as $event) {
            $id = (int) ($event['id'] ?? 0);
            $title = (string) ($event['title'] ?? '');
            $type = (string) ($event['type'] ?? '');
            $status = (string) ($event['status'] ?? '');
            $startAt = (string) ($event['start_at'] ?? '');
            $endAt = (string) ($event['end_at'] ?? '');
            $channels = (string) ($event['channels'] ?? '');
            $location = (string) ($event['location_name'] ?? '');
            $boothFee = (int) ($event['booth_fee_cents'] ?? 0);
            $otherCosts = (int) ($event['other_costs_cents'] ?? 0);
            $costs = $this->format_euros($boothFee + $otherCosts);

            $editUrl = admin_url('admin.php?page=' . self::EDIT_SLUG . '&event_id=' . $id);
            $duplicateUrl = $this->build_action_url('duplicate', $id, $filters);
            $cancelUrl = $this->build_action_url('cancel', $id, $filters);
            $activatePosUrl = $this->build_action_url('activate_pos', $id, $filters);

            echo '<tr>';
            echo '<td><input type="checkbox" name="event_ids[]" value="' . esc_attr((string) $id) . '" /></td>';
            echo '<td><a href="' . esc_url($editUrl) . '">' . esc_html((string) $id) . '</a></td>';
            echo '<td>' . esc_html($title) . '</td>';
            echo '<td>' . esc_html($type) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . esc_html($startAt) . '</td>';
            echo '<td>' . esc_html($endAt) . '</td>';
            echo '<td>' . esc_html($channels) . '</td>';
            echo '<td>' . esc_html($location) . '</td>';
            echo '<td>' . esc_html($costs) . '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url($editUrl) . '">Editar</a> ';
            echo '<a class="button button-small" href="' . esc_url($duplicateUrl) . '">Duplicar</a> ';
            echo '<a class="button button-small" href="' . esc_url($cancelUrl) . '">Cancelar</a>';
            echo '<a class="button button-small" href="' . esc_url($activatePosUrl) . '">Activar en POS</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" class="button">Cancelar seleccionados</button></p>';
        echo '</form>';

        echo $this->render_pagination($page, $totalPages, $filters);
        echo '</div>';
    }

    public function renderEditPage(): void
    {
        if (!$this->current_user_can()) {
            wp_die('No autorizado.');
        }

        $eventId = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $event = $eventId > 0 ? $this->repository->find_by_id($eventId) : null;

        $saved = false;
        $fieldErrors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bressol_events_save_submit'])) {
            check_admin_referer('bressol_events_save');
            $payload = $this->get_payload_from_request($_POST);

            if ($eventId > 0) {
                $saved = $this->service->update($eventId, $payload, get_current_user_id());
            } else {
                $newId = $this->service->create($payload, get_current_user_id());
                if ($newId > 0) {
                    $eventId = $newId;
                    $event = $this->repository->find_by_id($eventId);
                    $saved = true;
                }
            }

            if ($saved) {
                add_settings_error('bressol_markets_events', 'event_saved', 'Evento guardado.', 'updated');
                if ($eventId > 0) {
                    $this->handle_documents_save($eventId, $_POST, $_FILES);
                    $this->handle_cost_items_save($eventId, $_POST);
                }
            } else {
                add_settings_error('bressol_markets_events', 'event_save_failed', 'No se pudo guardar el evento.', 'error');
                $normalized = $this->service->normalize($payload);
                $fieldErrors = $this->service->validate($normalized);
            }
        }

        $defaults = [
            'title' => '',
            'type' => 'event',
            'status' => 'planned',
            'start_at' => '',
            'end_at' => '',
            'timezone' => wp_timezone_string() ?: 'UTC',
            'location_name' => '',
            'address' => '',
            'google_maps_url' => '',
            'distance_km' => '',
            'travel_time_min' => '',
            'booth_fee_cents' => 0,
            'other_costs_cents' => 0,
            'expected_sales_cents' => '',
            'notes' => '',
            'channels' => 'both',
        ];

        $data = $event ? array_merge($defaults, $event) : $defaults;

        echo '<div class="wrap">';
        echo '<h1>' . ($eventId > 0 ? 'Editar evento' : 'Nuevo evento') . '</h1>';
        settings_errors('bressol_markets_events');
        if ($eventId > 0) {
            echo '<p><strong>Event ID:</strong> ' . esc_html((string) $eventId) . '</p>';
        }

        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('bressol_events_save');
        echo '<table class="form-table">';
        echo $this->render_text_row('Título', 'title', (string) $data['title'], true, $fieldErrors['title'] ?? '');
        echo $this->render_select_row('Tipo', 'type', (string) $data['type'], [
            'market' => 'Market',
            'event' => 'Event',
            'delivery' => 'Delivery',
            'other' => 'Other',
        ], $fieldErrors['type'] ?? '');
        echo $this->render_select_row('Status', 'status', (string) $data['status'], [
            'planned' => 'Planned',
            'confirmed' => 'Confirmed',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
        ], $fieldErrors['status'] ?? '');
        echo $this->render_datetime_row('Inicio', 'start_at', (string) $data['start_at'], true, $fieldErrors['start_at'] ?? '');
        echo $this->render_datetime_row('Fin', 'end_at', (string) $data['end_at'], true, $fieldErrors['end_at'] ?? '');
        echo $this->render_text_row('Timezone', 'timezone', (string) $data['timezone'], true, $fieldErrors['timezone'] ?? '');
        echo $this->render_text_row('Ubicación', 'location_name', (string) $data['location_name'], true, $fieldErrors['location_name'] ?? '');
        echo $this->render_textarea_row('Dirección', 'address', (string) $data['address'], true, $fieldErrors['address'] ?? '');
        echo $this->render_text_row('Google Maps URL', 'google_maps_url', (string) ($data['google_maps_url'] ?? ''), false, $fieldErrors['google_maps_url'] ?? '');
        echo $this->render_text_row('Distancia (km)', 'distance_km', (string) ($data['distance_km'] ?? ''), false, $fieldErrors['distance_km'] ?? '');
        echo $this->render_text_row('Tiempo viaje (min)', 'travel_time_min', (string) ($data['travel_time_min'] ?? ''), false, $fieldErrors['travel_time_min'] ?? '');
        echo $this->render_text_row('Coste stand (€)', 'booth_fee_cents', $this->format_euros((int) ($data['booth_fee_cents'] ?? 0)), true, $fieldErrors['booth_fee_cents'] ?? '');
        echo $this->render_text_row('Otros costes (€)', 'other_costs_cents', $this->format_euros((int) ($data['other_costs_cents'] ?? 0)), true, $fieldErrors['other_costs_cents'] ?? '');
        echo $this->render_text_row('Ventas esperadas (€)', 'expected_sales_cents', $this->format_optional_euros($data['expected_sales_cents'] ?? null), false, $fieldErrors['expected_sales_cents'] ?? '');
        echo $this->render_textarea_row('Notas (interno)', 'notes', (string) ($data['notes'] ?? ''), false, $fieldErrors['notes'] ?? '');
        echo $this->render_select_row('Canal', 'channels', (string) $data['channels'], [
            'pos' => 'POS',
            'web' => 'Web',
            'both' => 'Both',
        ], $fieldErrors['channels'] ?? '');
        echo '</table>';

        $documents = $eventId > 0 ? (new EventDocumentsRepository())->list_by_event($eventId) : [];
        $costItems = $eventId > 0 ? (new EventCostItemsRepository())->list_by_event($eventId) : [];
        $this->render_documents_section($eventId, $documents);
        $this->render_cost_items_section($eventId, $documents, $costItems);

        echo '<p class="submit"><button type="submit" name="bressol_events_save_submit" class="button button-primary">Guardar</button></p>';
        echo '</form>';
        echo '</div>';
    }

    private function handle_list_actions(): void
    {
        if (!$this->current_user_can()) {
            return;
        }

        // 1) Bulk cancel via POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bressol_events_action'])) {
            $action = sanitize_key((string) wp_unslash($_POST['bressol_events_action']));

            if ($action === 'bulk_cancel') {
                check_admin_referer('bressol_events_bulk_cancel', '_bressol_events_nonce');
                $ids = isset($_POST['event_ids']) && is_array($_POST['event_ids'])
                    ? array_map('absint', $_POST['event_ids'])
                    : [];
                $updated = 0;
                foreach ($ids as $id) {
                    if ($id <= 0) {
                        continue;
                    }
                    $event = $this->repository->find_by_id($id);
                    if (!$event) {
                        continue;
                    }
                    $event['status'] = 'cancelled';
                    if ($this->service->update($id, $event, get_current_user_id())) {
                        $updated++;
                    }
                }

                $notice = $updated > 0 ? 'events_cancelled' : 'events_cancel_failed';
                $this->redirect_with_notice($notice);
            }

            $this->redirect_with_notice('event_invalid');
            return;
        }

        // 2) Row actions via GET + nonce
        if (empty($_GET['bressol_events_action'])) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_GET['bressol_events_action']));
        $eventId = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        if ($eventId <= 0) {
            $this->redirect_with_notice('event_invalid');
        }

        if ($action === 'duplicate') {
            check_admin_referer('bressol_events_duplicate_' . $eventId);
            $newId = $this->service->duplicate($eventId, get_current_user_id());
            $this->redirect_with_notice($newId > 0 ? 'event_duplicated' : 'event_duplicate_failed');
        }

        if ($action === 'cancel') {
            check_admin_referer('bressol_events_cancel_' . $eventId);
            $event = $this->repository->find_by_id($eventId);
            if (!$event) {
                $this->redirect_with_notice('event_not_found');
            }
            $event['status'] = 'cancelled';
            $ok = $this->service->update($eventId, $event, get_current_user_id());
            $this->redirect_with_notice($ok ? 'event_cancelled' : 'event_cancel_failed');
        }

        if ($action === 'activate_pos') {
            check_admin_referer('bressol_events_activate_pos_' . $eventId);
            $event = $this->repository->find_by_id($eventId);
            if (!$event) {
                $this->redirect_with_notice('event_not_found');
            }
            $channels = (string) ($event['channels'] ?? '');
            $allowed = (string) ($event['status'] ?? '') === 'confirmed'
                && in_array($channels, ['pos', 'both'], true);
            if (!$allowed) {
                $this->redirect_with_notice('event_pos_not_allowed');
            }
            $contextService = new PosEventContextService();
            $contextService->set_active_event_id($eventId, get_current_user_id());
            $activeId = $contextService->get_active_event_id(get_current_user_id());
            $this->redirect_with_notice($activeId === $eventId ? 'event_pos_set_ok' : 'event_pos_set_fail');
        }

        $this->redirect_with_notice('event_invalid');
    }

    private function hydrate_notice_from_query(): void
    {
        $notice = isset($_GET['me_notice']) ? sanitize_key((string) wp_unslash($_GET['me_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $map = [
            'events_cancelled' => ['Eventos cancelados.', 'updated'],
            'events_cancel_failed' => ['No se pudieron cancelar eventos.', 'error'],
            'event_invalid' => ['Evento inválido.', 'error'],
            'event_duplicated' => ['Evento duplicado.', 'updated'],
            'event_duplicate_failed' => ['No se pudo duplicar el evento.', 'error'],
            'event_not_found' => ['Evento no encontrado.', 'error'],
            'event_cancelled' => ['Evento cancelado.', 'updated'],
            'event_cancel_failed' => ['No se pudo cancelar el evento.', 'error'],
            'event_pos_set_ok' => ['Evento activo POS actualizado.', 'updated'],
            'event_pos_set_fail' => ['No se pudo activar en POS.', 'error'],
            'event_pos_not_allowed' => ['Evento no permitido para POS.', 'error'],
        ];

        if (!isset($map[$notice])) {
            return;
        }

        [$msg, $type] = $map[$notice];
        add_settings_error('bressol_markets_events', $notice, $msg, $type);
    }

    private function redirect_with_notice(string $notice): void
    {
        $filters = $this->get_filters_from_request($_REQUEST);
        $paged = isset($_REQUEST['paged']) ? max(1, absint($_REQUEST['paged'])) : 1;
        $args = [
            'page' => self::PAGE_SLUG,
            'me_notice' => $notice,
            'status' => $filters['status'],
            'type' => $filters['type'],
            'channel' => $filters['channel'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'paged' => $paged,
        ];
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /** @param array<string,string> $filters */
    private function build_action_url(string $action, int $eventId, array $filters): string
    {
        $args = [
            'page' => self::PAGE_SLUG,
            'bressol_events_action' => $action,
            'event_id' => $eventId,
            'status' => $filters['status'] ?? '',
            'type' => $filters['type'] ?? '',
            'channel' => $filters['channel'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
        ];
        $base = add_query_arg($args, admin_url('admin.php'));
        $nonceAction = 'bressol_events_' . $action . '_' . $eventId;
        return wp_nonce_url($base, $nonceAction);
    }

    /** @param array<string, mixed> $filters */
    private function render_filters_form(array $filters): string
    {
        $html = '<form method="get" style="margin:16px 0;">';
        $html .= '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';
        $html .= '<label>Status ';
        $html .= $this->render_select('status', (string) $filters['status'], [
            '' => 'Todos',
            'planned' => 'Planned',
            'confirmed' => 'Confirmed',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
        ]);
        $html .= '</label> ';
        $html .= '<label>Tipo ';
        $html .= $this->render_select('type', (string) $filters['type'], [
            '' => 'Todos',
            'market' => 'Market',
            'event' => 'Event',
            'delivery' => 'Delivery',
            'other' => 'Other',
        ]);
        $html .= '</label> ';
        $html .= '<label>Canal ';
        $html .= $this->render_select('channel', (string) $filters['channel'], [
            '' => 'Todos',
            'pos' => 'POS',
            'web' => 'Web',
            'both' => 'Both',
        ]);
        $html .= '</label> ';
        $html .= '<label>Desde <input type="date" name="date_from" value="' . esc_attr((string) $filters['date_from']) . '" /></label> ';
        $html .= '<label>Hasta <input type="date" name="date_to" value="' . esc_attr((string) $filters['date_to']) . '" /></label> ';
        $html .= '<button class="button">Filtrar</button>';
        $html .= '</form>';

        return $html;
    }

    /** @param array<string, string> $filters */
    private function render_pagination(int $page, int $totalPages, array $filters): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $page = min(max(1, $page), $totalPages);
        $baseArgs = [
            'page' => self::PAGE_SLUG,
            'status' => $filters['status'],
            'type' => $filters['type'],
            'channel' => $filters['channel'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];

        $html = '<div class="tablenav"><div class="tablenav-pages">';
        $html .= '<span class="displaying-num">' . esc_html($page . ' / ' . $totalPages) . '</span> ';
        $html .= $this->pagination_link($page <= 1, 1, '« Primera', $baseArgs);
        $html .= $this->pagination_link($page <= 1, $page - 1, '‹ Anterior', $baseArgs);
        $html .= $this->pagination_link($page >= $totalPages, $page + 1, 'Siguiente ›', $baseArgs);
        $html .= $this->pagination_link($page >= $totalPages, $totalPages, 'Última »', $baseArgs);
        $html .= '</div></div>';

        return $html;
    }

    /** @param array<string, string> $baseArgs */
    private function pagination_link(bool $disabled, int $page, string $label, array $baseArgs): string
    {
        if ($disabled) {
            return '<span class="tablenav-pages-navspan" aria-hidden="true">' . esc_html($label) . '</span> ';
        }

        $url = add_query_arg(array_merge($baseArgs, ['paged' => $page]), admin_url('admin.php'));
        return '<a class="button" href="' . esc_url($url) . '">' . esc_html($label) . '</a> ';
    }

    /** @param array<string, mixed> $input
     *  @return array<string, string>
     */
    private function get_filters_from_request(array $input): array
    {
        $status = isset($input['status']) ? sanitize_key((string) wp_unslash($input['status'])) : '';
        $type = isset($input['type']) ? sanitize_key((string) wp_unslash($input['type'])) : '';
        $channel = isset($input['channel']) ? sanitize_key((string) wp_unslash($input['channel'])) : '';
        $dateFrom = isset($input['date_from']) ? sanitize_text_field(wp_unslash($input['date_from'])) : '';
        $dateTo = isset($input['date_to']) ? sanitize_text_field(wp_unslash($input['date_to'])) : '';

        return [
            'status' => in_array($status, ['planned', 'confirmed', 'done', 'cancelled'], true) ? $status : '',
            'type' => in_array($type, ['market', 'event', 'delivery', 'other'], true) ? $type : '',
            'channel' => in_array($channel, ['pos', 'web', 'both'], true) ? $channel : '',
            'date_from' => $this->normalize_date($dateFrom),
            'date_to' => $this->normalize_date($dateTo),
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function get_payload_from_request(array $input): array
    {
        return [
            'title' => isset($input['title']) ? sanitize_text_field(wp_unslash($input['title'])) : '',
            'type' => isset($input['type']) ? sanitize_key((string) wp_unslash($input['type'])) : 'event',
            'status' => isset($input['status']) ? sanitize_key((string) wp_unslash($input['status'])) : 'planned',
            'start_at' => $this->normalize_datetime_input(isset($input['start_at']) ? wp_unslash($input['start_at']) : ''),
            'end_at' => $this->normalize_datetime_input(isset($input['end_at']) ? wp_unslash($input['end_at']) : ''),
            'timezone' => isset($input['timezone']) ? sanitize_text_field(wp_unslash($input['timezone'])) : '',
            'location_name' => isset($input['location_name']) ? sanitize_text_field(wp_unslash($input['location_name'])) : '',
            'address' => isset($input['address']) ? sanitize_textarea_field(wp_unslash($input['address'])) : '',
            'google_maps_url' => isset($input['google_maps_url']) ? sanitize_text_field(wp_unslash($input['google_maps_url'])) : '',
            'distance_km' => isset($input['distance_km']) ? sanitize_text_field(wp_unslash($input['distance_km'])) : '',
            'travel_time_min' => isset($input['travel_time_min']) ? sanitize_text_field(wp_unslash($input['travel_time_min'])) : '',
            'booth_fee_cents' => $this->parse_euros_to_cents(isset($input['booth_fee_cents']) ? wp_unslash($input['booth_fee_cents']) : '0'),
            'other_costs_cents' => $this->parse_euros_to_cents(isset($input['other_costs_cents']) ? wp_unslash($input['other_costs_cents']) : '0'),
            'expected_sales_cents' => $this->parse_optional_euros_to_cents(isset($input['expected_sales_cents']) ? wp_unslash($input['expected_sales_cents']) : ''),
            'notes' => isset($input['notes']) ? sanitize_textarea_field(wp_unslash($input['notes'])) : '',
            'channels' => isset($input['channels']) ? sanitize_key((string) wp_unslash($input['channels'])) : 'both',
        ];
    }

    private function render_text_row(string $label, string $name, string $value, bool $required, string $errorMessage = ''): string
    {
        $req = $required ? 'required' : '';
        $html = '<tr><th>' . esc_html($label) . '</th><td><input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . $req . ' class="regular-text" />';
        if ($errorMessage !== '') {
            $html .= $this->render_field_error($errorMessage);
        }
        $html .= '</td></tr>';
        return $html;
    }

    private function render_datetime_row(string $label, string $name, string $value, bool $required, string $errorMessage = ''): string
    {
        $req = $required ? 'required' : '';
        $formatted = $this->format_datetime_local($value);
        $html = '<tr><th>' . esc_html($label) . '</th><td><input type="datetime-local" name="'
            . esc_attr($name) . '" value="' . esc_attr($formatted) . '" ' . $req . ' class="regular-text" />';
        if ($errorMessage !== '') {
            $html .= $this->render_field_error($errorMessage);
        }
        $html .= '</td></tr>';
        return $html;
    }

    /** @param array<string, string> $options */
    private function render_select_row(string $label, string $name, string $value, array $options, string $errorMessage = ''): string
    {
        $html = '<tr><th>' . esc_html($label) . '</th><td>' . $this->render_select($name, $value, $options);
        if ($errorMessage !== '') {
            $html .= $this->render_field_error($errorMessage);
        }
        $html .= '</td></tr>';
        return $html;
    }

    private function render_textarea_row(string $label, string $name, string $value, bool $required, string $errorMessage = ''): string
    {
        $req = $required ? 'required' : '';
        $html = '<tr><th>' . esc_html($label) . '</th><td><textarea name="' . esc_attr($name) . '" rows="4" cols="50" ' . $req . '>' . esc_textarea($value) . '</textarea>';
        if ($errorMessage !== '') {
            $html .= $this->render_field_error($errorMessage);
        }
        $html .= '</td></tr>';
        return $html;
    }

    /** @param array<int, array<string, mixed>> $documents */
    private function render_documents_section(int $eventId, array $documents): void
    {
        echo '<h2>Documents</h2>';
        if ($eventId <= 0) {
            echo '<p class="description">Guarda el evento antes de subir documentos.</p>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:840px;">';
        echo '<thead><tr><th>ID</th><th>Documento</th><th>Tipo</th><th>Fecha</th><th>Eliminar</th></tr></thead><tbody>';
        if ($documents === []) {
            echo '<tr><td colspan="5">No hay documentos.</td></tr>';
        } else {
            foreach ($documents as $doc) {
                $id = (int) ($doc['id'] ?? 0);
                $title = (string) ($doc['title'] ?? '');
                $url = (string) ($doc['file_url'] ?? '');
                $mime = (string) ($doc['mime_type'] ?? '');
                $createdAt = (string) ($doc['created_at'] ?? '');
                $label = $title !== '' ? $title : ('Document #' . $id);
                echo '<tr>';
                echo '<td>' . esc_html((string) $id) . '</td>';
                echo '<td>' . ($url !== '' ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($label) . '</a>' : esc_html($label)) . '</td>';
                echo '<td>' . esc_html($mime) . '</td>';
                echo '<td>' . esc_html($createdAt) . '</td>';
                echo '<td><label><input type="checkbox" name="delete_document_ids[]" value="' . esc_attr((string) $id) . '" /> eliminar</label></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        echo '<p style="margin-top:12px;"><label>Subir documento: <input type="file" name="bressol_event_document" accept="application/pdf,image/*" /></label></p>';
    }

    /** @param array<int, array<string, mixed>> $documents
     *  @param array<int, array<string, mixed>> $costItems
     */
    private function render_cost_items_section(int $eventId, array $documents, array $costItems): void
    {
        echo '<h2>Cost items</h2>';
        if ($eventId <= 0) {
            echo '<p class="description">Guarda el evento antes de añadir costes.</p>';
            return;
        }

        $documentIds = [];
        foreach ($documents as $doc) {
            $docId = (int) ($doc['id'] ?? 0);
            if ($docId > 0) {
                $documentIds[$docId] = $doc;
            }
        }

        echo '<table class="widefat striped" style="max-width:980px;">';
        echo '<thead><tr><th>Eliminar</th><th>Categoria</th><th>Amount (cents)</th><th>Incurred at</th><th>Note</th><th>Doc ID</th></tr></thead><tbody>';

        $rows = $costItems;
        $extra = 5;
        for ($i = 0; $i < $extra; $i++) {
            $rows[] = [
                'id' => 0,
                'category' => '',
                'amount_cents' => '',
                'incurred_at' => '',
                'note' => '',
                'event_document_id' => '',
            ];
        }

        foreach ($rows as $item) {
            $id = (int) ($item['id'] ?? 0);
            $category = (string) ($item['category'] ?? '');
            $amount = $item['amount_cents'] ?? '';
            $incurred = (string) ($item['incurred_at'] ?? '');
            $note = (string) ($item['note'] ?? '');
            $docId = $item['event_document_id'] ?? '';

            echo '<tr>';
            echo '<td>';
            if ($id > 0) {
                echo '<input type="checkbox" name="delete_cost_item_ids[]" value="' . esc_attr((string) $id) . '" />';
            } else {
                echo '&mdash;';
            }
            echo '<input type="hidden" name="cost_item_id[]" value="' . esc_attr((string) $id) . '" />';
            echo '</td>';
            echo '<td>' . $this->render_cost_category_select($category) . '</td>';
            echo '<td><input type="number" name="cost_item_amount_cents[]" value="' . esc_attr((string) $amount) . '" /></td>';
            echo '<td><input type="date" name="cost_item_incurred_at[]" value="' . esc_attr($incurred) . '" /></td>';
            echo '<td><input type="text" name="cost_item_note[]" value="' . esc_attr($note) . '" /></td>';
            echo '<td><input type="number" name="cost_item_document_id[]" value="' . esc_attr((string) $docId) . '" /></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        if ($documentIds !== []) {
            echo '<p class="description">Docs disponibles: ';
            $parts = [];
            foreach ($documentIds as $docId => $doc) {
                $label = (string) ($doc['title'] ?? ('Doc #' . $docId));
                $parts[] = $docId . ' - ' . $label;
            }
            echo esc_html(implode(', ', $parts));
            echo '</p>';
        }
    }

    private function render_cost_category_select(string $value): string
    {
        $options = [
            'booth_fee' => 'booth_fee',
            'reservation' => 'reservation',
            'parking' => 'parking',
            'hotel' => 'hotel',
            'fuel' => 'fuel',
            'tolls' => 'tolls',
            'supplies' => 'supplies',
            'refund' => 'refund',
            'correction' => 'correction',
            'other' => 'other',
        ];

        $html = '<select name="cost_item_category[]">';
        foreach ($options as $key => $label) {
            $html .= '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    /** @param array<string, mixed> $input
     *  @param array<string, mixed> $files
     */
    private function handle_documents_save(int $eventId, array $input, array $files): void
    {
        if ($eventId <= 0) {
            return;
        }

        $repo = new EventDocumentsRepository();
        $deleteIds = isset($input['delete_document_ids']) && is_array($input['delete_document_ids'])
            ? array_map('absint', $input['delete_document_ids'])
            : [];
        if ($deleteIds !== []) {
            $repo->delete_by_ids($eventId, $deleteIds);
        }

        if (empty($files['bressol_event_document']) || !is_array($files['bressol_event_document'])) {
            return;
        }

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachmentId = media_handle_upload('bressol_event_document', 0);
        if (is_wp_error($attachmentId)) {
            add_settings_error('bressol_markets_events', 'event_doc_upload_failed', 'No se pudo subir el documento.', 'error');
            return;
        }

        $fileUrl = wp_get_attachment_url($attachmentId);
        $mimeType = get_post_mime_type($attachmentId);
        $title = get_the_title($attachmentId);

        if (!$fileUrl || !$mimeType) {
            add_settings_error('bressol_markets_events', 'event_doc_invalid', 'Documento inválido.', 'error');
            return;
        }

        $repo->insert_document($eventId, (int) $attachmentId, (string) $title, (string) $mimeType, (string) $fileUrl);
    }

    /** @param array<string, mixed> $input */
    private function handle_cost_items_save(int $eventId, array $input): void
    {
        if ($eventId <= 0) {
            return;
        }

        $repo = new EventCostItemsRepository();
        $deleteIds = isset($input['delete_cost_item_ids']) && is_array($input['delete_cost_item_ids'])
            ? array_map('absint', $input['delete_cost_item_ids'])
            : [];
        if ($deleteIds !== []) {
            $repo->delete_by_ids($eventId, $deleteIds);
        }

        $ids = isset($input['cost_item_id']) && is_array($input['cost_item_id']) ? $input['cost_item_id'] : [];
        $categories = isset($input['cost_item_category']) && is_array($input['cost_item_category']) ? $input['cost_item_category'] : [];
        $amounts = isset($input['cost_item_amount_cents']) && is_array($input['cost_item_amount_cents']) ? $input['cost_item_amount_cents'] : [];
        $incurred = isset($input['cost_item_incurred_at']) && is_array($input['cost_item_incurred_at']) ? $input['cost_item_incurred_at'] : [];
        $notes = isset($input['cost_item_note']) && is_array($input['cost_item_note']) ? $input['cost_item_note'] : [];
        $docIds = isset($input['cost_item_document_id']) && is_array($input['cost_item_document_id']) ? $input['cost_item_document_id'] : [];

        $items = [];
        $count = min(max(count($ids), count($categories), count($amounts), count($incurred)), 200);
        for ($i = 0; $i < $count; $i++) {
            $id = isset($ids[$i]) ? absint($ids[$i]) : 0;
            $category = isset($categories[$i]) ? sanitize_key((string) wp_unslash($categories[$i])) : 'other';
            $amountRaw = isset($amounts[$i]) ? wp_unslash($amounts[$i]) : '';
            $amount = is_numeric($amountRaw) ? (int) $amountRaw : 0;
            $incurredAt = isset($incurred[$i]) ? sanitize_text_field(wp_unslash($incurred[$i])) : '';
            $note = isset($notes[$i]) ? sanitize_text_field(wp_unslash($notes[$i])) : '';
            $docId = isset($docIds[$i]) ? absint($docIds[$i]) : 0;

            if ($category === '') {
                $category = 'other';
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $incurredAt)) {
                $incurredAt = current_time('Y-m-d');
            }

            if ($id === 0 && $amount === 0 && $note === '' && $docId === 0) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'category' => $category,
                'amount_cents' => $amount,
                'incurred_at' => $incurredAt,
                'note' => $note !== '' ? $note : null,
                'event_document_id' => $docId > 0 ? $docId : null,
            ];
        }

        if ($items !== []) {
            $repo->upsert_items($eventId, $items);
        }
    }

    private function render_field_error(string $message): string
    {
        return '<p class="description" style="color:#b32d2e">' . esc_html($message) . '</p>';
    }

    /** @param array<string, string> $options */
    private function render_select(string $name, string $value, array $options): string
    {
        $html = '<select name="' . esc_attr($name) . '">';
        foreach ($options as $key => $label) {
            $html .= '<option value="' . esc_attr($key) . '" ' . selected($value, (string) $key, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function normalize_datetime_input(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        if (strpos($raw, 'T') !== false) {
            $raw = str_replace('T', ' ', $raw);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) {
            return sanitize_text_field($raw);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
            return sanitize_text_field($raw . ':00');
        }

        return '';
    }

    private function format_datetime_local(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return str_replace(' ', 'T', substr($value, 0, 16));
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return str_replace(' ', 'T', $value);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
            return substr($value, 0, 16);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return '';
    }

    private function normalize_date(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    private function parse_euros_to_cents($value): int
    {
        $raw = is_string($value) ? $value : (string) $value;
        $raw = str_replace(',', '.', $raw);
        $float = (float) $raw;
        $cents = (int) round($float * 100);

        return max(0, $cents);
    }

    private function parse_optional_euros_to_cents($value): ?int
    {
        $raw = is_string($value) ? trim($value) : (string) $value;
        if ($raw === '') {
            return null;
        }

        return $this->parse_euros_to_cents($raw);
    }

    private function format_euros(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function format_optional_euros($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->format_euros((int) $value);
    }

    private function get_capability(): string
    {
        return $this->capabilities->get_events_capability();
    }

    private function current_user_can(): bool
    {
        return current_user_can($this->capabilities->get_events_capability());
    }
}
