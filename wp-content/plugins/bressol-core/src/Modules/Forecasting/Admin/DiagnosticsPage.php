<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Admin;

use Bressol\Modules\Forecasting\Repositories\ForecastSnapshotRepository;
use Bressol\Modules\Forecasting\Services\ForecastCalculator;
use Bressol\Modules\Forecasting\Services\PosSalesSnapshotBuilder;
use Bressol\Modules\Forecasting\Services\SelfTestService;
use Bressol\Modules\MarketsEvents\Repositories\EventRepository;
use Bressol\Modules\MarketsEvents\Services\PosEventEligibilityService;
use Bressol\Modules\MarketsEvents\Services\OrderEventMetaService;

if (!defined('ABSPATH')) {
    exit;
}

final class DiagnosticsPage
{
    private const ACTION_SELFTEST = 'bressol_forecasting_selftest';
    private const NONCE_ACTION = 'bressol_forecasting_selftest';
    private const ACTION_RUN = 'bressol_forecasting_run';
    private const NONCE_RUN = 'bressol_forecasting_run';
    private const ACTION_LOAD_SNAPSHOT = 'bressol_forecasting_load_snapshot';
    private const NONCE_LOAD_SNAPSHOT = 'bressol_forecasting_load_snapshot';
    private const ACTION_SAVE_SNAPSHOT = 'bressol_forecasting_save_snapshot';
    private const NONCE_SAVE_SNAPSHOT = 'bressol_forecasting_save_snapshot';
    private const ACTION_GENERATE_POS_SNAPSHOT = 'bressol_forecasting_generate_pos_snapshot';
    private const NONCE_GENERATE_POS_SNAPSHOT = 'bressol_forecasting_generate_pos_snapshot';
    private const ACTION_BACKFILL_POS_SNAPSHOTS = 'bressol_forecasting_backfill_pos_snapshots';
    private const NONCE_BACKFILL_POS_SNAPSHOTS = 'bressol_forecasting_backfill_pos_snapshots';
    private const ACTION_POS_SNAPSHOT_DEBUG = 'bressol_forecasting_pos_snapshot_debug';
    private const NONCE_POS_SNAPSHOT_DEBUG = 'bressol_forecasting_pos_snapshot_debug';
    private const RESULTS_TRANSIENT_PREFIX = 'bressol_forecasting_run_';

    public function render(): void
    {
        if (!current_user_can($this->get_capability())) {
            wp_die('No autorizado.');
        }

        $eventId = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $productId = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        $steps = $this->parse_steps_from_query($_GET);
        $tab = $this->get_active_tab();
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $posDebugResults = $this->parse_pos_debug_results_from_query($_GET);

        echo '<div class="wrap">';
        echo '<h1>Forecasting - Diagnostics</h1>';
        $this->render_tabs($tab, $token);

        if ($tab === 'results') {
            $this->render_results_tab($token);
        } elseif ($tab === 'snapshots') {
            $this->render_snapshots_tab($eventId);
        } else {
            $this->render_form($eventId, $productId);
            $this->render_pos_snapshot_debug_form();
            $this->render_run_form();
            if ($steps !== []) {
                $this->render_steps($steps);
            }
            if ($posDebugResults !== []) {
                $this->render_pos_snapshot_debug_results($posDebugResults);
            }
        }

        echo '</div>';
    }

    public function handleSelftest(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->redirect_with_notice('forbidden');
        }

        check_admin_referer(self::NONCE_ACTION);

        $eventId = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $productId = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if ($eventId <= 0 || $productId <= 0) {
            $this->redirect_with_notice('invalid_input', $eventId, $productId);
        }

        $service = new SelfTestService();
        $steps = $service->run($eventId, $productId);

        $payload = wp_json_encode($steps);
        if (!is_string($payload)) {
            $payload = '[]';
        }
        $encoded = base64_encode($payload);

        $this->redirect_with_steps($encoded, $eventId, $productId);
    }

    public function handleRunForecast(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->redirect_with_notice('forbidden');
        }

        check_admin_referer(self::NONCE_RUN);

        $calculator = new ForecastCalculator();
        $payload = $calculator->run();
        $payload['generated_at_utc'] = gmdate('Y-m-d H:i:s');

        $token = wp_generate_password(12, false, false);
        $key = self::RESULTS_TRANSIENT_PREFIX . $token;
        set_transient($key, $payload, 5 * MINUTE_IN_SECONDS);

        $this->redirect_to_results($token);
    }

    public function handleLoadSnapshot(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->redirect_with_notice('forbidden');
        }

        check_admin_referer(self::NONCE_LOAD_SNAPSHOT);

        $eventId = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $this->redirect_to_snapshots($eventId);
    }

    public function handleSaveSnapshot(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->redirect_with_notice('forbidden');
        }

        check_admin_referer(self::NONCE_SAVE_SNAPSHOT);

        $eventId = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        if ($eventId <= 0) {
            $this->redirect_to_snapshots(0, 'snapshot_invalid_event');
        }

        $lines = $this->parse_snapshot_lines($_POST);
        $repo = new ForecastSnapshotRepository();
        $snapshotId = $repo->upsert_manual_snapshot_for_event(
            $eventId,
            gmdate('Y-m-d H:i:s'),
            get_current_user_id(),
            null
        );
        if ($snapshotId <= 0) {
            $this->redirect_to_snapshots($eventId, 'snapshot_save_failed');
        }

        $repo->replace_lines($snapshotId, $lines);
        $this->redirect_to_snapshots($eventId, 'snapshot_saved');
    }

    public function handleGeneratePosSnapshot(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->redirect_with_notice('forbidden');
        }

        check_admin_referer(self::NONCE_GENERATE_POS_SNAPSHOT);

        $eventId = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        if ($eventId <= 0) {
            $this->redirect_to_snapshots(0, 'snapshot_invalid_event');
        }

        $builder = new PosSalesSnapshotBuilder();
        $result = $builder->build_for_event($eventId);
        $payload = $this->encode_snapshot_result($result);

        $notice = !empty($result['ok']) ? 'snapshot_pos_ok' : 'snapshot_pos_failed';
        $this->redirect_to_snapshots($eventId, $notice, $payload);
    }

    public function handleBackfillPosSnapshots(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->redirect_with_notice('forbidden');
        }

        check_admin_referer(self::NONCE_BACKFILL_POS_SNAPSHOTS);

        $range = $this->parse_backfill_range($_POST);
        $events = $this->load_events_for_backfill($range['from_utc'], $range['to_utc']);
        $builder = new PosSalesSnapshotBuilder();

        $results = [];
        foreach ($events as $event) {
            $eventId = isset($event['id']) ? (int) $event['id'] : 0;
            if ($eventId <= 0) {
                continue;
            }
            $result = $builder->build_for_event($eventId);
            $results[] = [
                'event_id' => $eventId,
                'ok' => !empty($result['ok']),
                'message' => !empty($result['ok'])
                    ? 'Snapshot generado (' . (int) ($result['lines_count'] ?? 0) . ' líneas)'
                    : (string) ($result['error'] ?? 'Error'),
                'warnings' => isset($result['warnings']) && is_array($result['warnings'])
                    ? $result['warnings']
                    : [],
            ];
        }

        $payload = $this->encode_snapshot_result([
            'backfill' => $results,
        ]);

        $this->redirect_to_snapshots(0, 'snapshot_backfill_done', $payload);
    }

    public function handlePosSnapshotDebug(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->redirect_with_notice('forbidden');
        }

        check_admin_referer(self::NONCE_POS_SNAPSHOT_DEBUG);

        $eligibilityService = class_exists(PosEventEligibilityService::class)
            ? new PosEventEligibilityService()
            : null;
        $eligible = $eligibilityService ? $eligibilityService->list_eligible_events(25) : [];

        $eventsPayload = [];
        foreach ($eligible as $event) {
            $eventsPayload[] = [
                'id' => (int) ($event['id'] ?? 0),
                'title' => (string) ($event['title'] ?? ''),
                'start_at' => (string) ($event['start_at'] ?? ''),
                'end_at' => (string) ($event['end_at'] ?? ''),
                'channels' => (string) ($event['channels'] ?? ''),
                'status' => (string) ($event['status'] ?? ''),
            ];
        }

        $eventId = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        if ($eventId <= 0 && !empty($eventsPayload[0]['id'])) {
            $eventId = (int) $eventsPayload[0]['id'];
        }

        $ordersCount = 0;
        if ($eventId > 0 && function_exists('wc_get_orders') && class_exists(OrderEventMetaService::class)) {
            $orders = wc_get_orders([
                'status' => 'completed',
                'return' => 'ids',
                'limit' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_bressol_pos_channel',
                        'value' => 'pos',
                        'compare' => '=',
                    ],
                    [
                        'key' => OrderEventMetaService::META_KEY,
                        'value' => (string) $eventId,
                        'compare' => '=',
                    ],
                ],
            ]);
            $ordersCount = is_array($orders) ? count($orders) : 0;
        }

        $snapshotResult = [];
        if ($eventId > 0) {
            $builder = new PosSalesSnapshotBuilder();
            $snapshotResult = $builder->build_for_event($eventId);
        }

        $repo = new ForecastSnapshotRepository();
        $latestSnapshot = $eventId > 0 ? $repo->get_latest_snapshot_for_event_and_source($eventId, 'pos') : null;
        $latestSnapshotId = is_array($latestSnapshot) ? (int) ($latestSnapshot['id'] ?? 0) : 0;
        $latestLines = $latestSnapshotId > 0 ? $repo->get_lines_for_snapshot($latestSnapshotId) : [];

        $payload = [
            'eligible_events' => $eventsPayload,
            'selected_event_id' => $eventId,
            'orders_count' => $ordersCount,
            'snapshot_result' => [
                'orders_count' => (int) ($snapshotResult['orders_count'] ?? 0),
                'refunds_count' => (int) ($snapshotResult['refunds_count'] ?? 0),
                'lines_count' => (int) ($snapshotResult['lines_count'] ?? 0),
                'warnings' => isset($snapshotResult['warnings']) && is_array($snapshotResult['warnings'])
                    ? $snapshotResult['warnings']
                    : [],
                'error' => isset($snapshotResult['error']) ? (string) $snapshotResult['error'] : '',
            ],
            'latest_snapshot' => [
                'snapshot_id' => $latestSnapshotId,
                'lines_count' => is_array($latestLines) ? count($latestLines) : 0,
            ],
        ];

        $encoded = $this->encode_snapshot_result($payload);
        $this->redirect_with_pos_debug_results($encoded, $eventId);
    }

    /** @return array<int, array{step:string,ok:bool,message:string}> */
    private function parse_steps_from_query(array $query): array
    {
        if (empty($query['steps'])) {
            return [];
        }

        $raw = sanitize_text_field(wp_unslash((string) $query['steps']));
        if ($raw === '') {
            return [];
        }

        $decoded = base64_decode($raw, true);
        if (!is_string($decoded) || $decoded === '') {
            return [];
        }

        $steps = json_decode($decoded, true);
        if (!is_array($steps)) {
            return [];
        }

        $out = [];
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $out[] = [
                'step' => isset($step['step']) ? sanitize_key((string) $step['step']) : '',
                'ok' => !empty($step['ok']),
                'message' => isset($step['message']) ? sanitize_text_field((string) $step['message']) : '',
            ];
        }

        return $out;
    }

    private function render_form(int $eventId, int $productId): void
    {
        $productValue = $productId > 0 ? (string) $productId : '';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        wp_nonce_field(self::NONCE_ACTION);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SELFTEST) . '" />';

        echo '<table class="form-table" style="max-width:720px;">';

        $events = $this->load_recent_events_for_select();
        if ($events === []) {
            echo '<tr><th>Event ID</th><td>';
            echo '<p class="description" style="margin:0 0 6px 0;">No hay eventos recientes. Usa ID manual.</p>';
            echo '<input type="number" min="1" name="event_id" value="' . esc_attr((string) ($eventId > 0 ? $eventId : '')) . '" required />';
            echo '</td></tr>';
        } else {
            echo '<tr><th>Event</th><td>';
            echo '<select name="event_id" required style="min-width:420px;">';
            echo '<option value="">— Selecciona un evento —</option>';

            foreach ($events as $e) {
                $id = (int) ($e['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $label = $this->format_event_label($e);
                $selected = $eventId === $id ? ' selected' : '';
                echo '<option value="' . esc_attr((string) $id) . '"' . $selected . '>' . esc_html($label) . '</option>';
            }

            echo '</select>';
            echo '<p class="description">Tip: puedes ver el ID en Markets &amp; Events (columna ID).</p>';
            echo '</td></tr>';
        }

        echo '<tr><th>Product ID</th><td><input type="number" min="1" name="product_id" value="' . esc_attr($productValue) . '" required /></td></tr>';
        echo '</table>';

        echo '<p class="submit"><button type="submit" class="button button-primary">Run selftest</button></p>';
        echo '</form>';
    }

    private function render_pos_snapshot_debug_form(): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:16px 0;">';
        wp_nonce_field(self::NONCE_POS_SNAPSHOT_DEBUG);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_POS_SNAPSHOT_DEBUG) . '" />';
        echo '<button type="submit" class="button">Run POS Snapshot Debug</button>';
        echo '</form>';
    }

    private function render_run_form(): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:16px 0;">';
        wp_nonce_field(self::NONCE_RUN);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_RUN) . '" />';
        echo '<button type="submit" class="button">Run forecast (6 weeks)</button>';
        echo '</form>';
    }

    /** @param array<int, array{step:string,ok:bool,message:string}> $steps */
    private function render_steps(array $steps): void
    {
        echo '<h2>Resultados</h2>';
        echo '<table class="widefat striped" style="max-width:780px;">';
        echo '<thead><tr><th>Step</th><th>Status</th><th>Message</th></tr></thead><tbody>';

        foreach ($steps as $step) {
            $status = $step['ok'] ? 'ok' : 'error';
            echo '<tr>';
            echo '<td>' . esc_html($step['step']) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . esc_html($step['message']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /** @param array<string, mixed> $payload */
    private function render_pos_snapshot_debug_results(array $payload): void
    {
        echo '<h2>POS Snapshot Debug</h2>';

        $events = isset($payload['eligible_events']) && is_array($payload['eligible_events'])
            ? $payload['eligible_events']
            : [];
        if ($events === []) {
            echo '<p class="description">No hay eventos elegibles hoy.</p>';
        } else {
            echo '<table class="widefat striped" style="max-width:900px;">';
            echo '<thead><tr><th>ID</th><th>Título</th><th>Inicio</th><th>Fin</th><th>Channels</th><th>Status</th></tr></thead><tbody>';
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }
                echo '<tr>';
                echo '<td>' . esc_html((string) ($event['id'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($event['title'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($event['start_at'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($event['end_at'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($event['channels'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($event['status'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        $selectedEventId = isset($payload['selected_event_id']) ? (int) $payload['selected_event_id'] : 0;
        $ordersCount = isset($payload['orders_count']) ? (int) $payload['orders_count'] : 0;
        echo '<p><strong>Selected event ID:</strong> ' . esc_html((string) $selectedEventId) . '</p>';
        echo '<p><strong>POS orders completed (event/meta match):</strong> ' . esc_html((string) $ordersCount) . '</p>';

        $snapshotResult = isset($payload['snapshot_result']) && is_array($payload['snapshot_result'])
            ? $payload['snapshot_result']
            : [];
        $snapshotError = isset($snapshotResult['error']) ? (string) $snapshotResult['error'] : '';
        if ($snapshotError !== '') {
            echo '<p class="notice notice-error" style="padding:8px 12px;">' . esc_html($snapshotError) . '</p>';
        }

        echo '<table class="widefat striped" style="max-width:520px;">';
        echo '<thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
        echo '<tr><td>orders_count</td><td>' . esc_html((string) ($snapshotResult['orders_count'] ?? 0)) . '</td></tr>';
        echo '<tr><td>refunds_count</td><td>' . esc_html((string) ($snapshotResult['refunds_count'] ?? 0)) . '</td></tr>';
        echo '<tr><td>lines_count</td><td>' . esc_html((string) ($snapshotResult['lines_count'] ?? 0)) . '</td></tr>';
        echo '</tbody></table>';

        $warnings = isset($snapshotResult['warnings']) && is_array($snapshotResult['warnings'])
            ? $snapshotResult['warnings']
            : [];
        if ($warnings !== []) {
            echo '<div class="notice notice-warning" style="padding:8px 12px;">';
            echo '<ul style="margin:6px 0 0 16px;">';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        $latest = isset($payload['latest_snapshot']) && is_array($payload['latest_snapshot'])
            ? $payload['latest_snapshot']
            : [];
        echo '<p><strong>Latest snapshot source=pos:</strong> '
            . esc_html((string) ($latest['snapshot_id'] ?? 0))
            . ' · lines: ' . esc_html((string) ($latest['lines_count'] ?? 0))
            . '</p>';
    }

    private function render_tabs(string $activeTab, string $token): void
    {
        $tabs = [
            'selftest' => 'Selftest',
            'results' => 'Results',
            'snapshots' => 'Snapshots',
        ];

        echo '<h2 class="nav-tab-wrapper" style="margin-bottom:16px;">';
        foreach ($tabs as $slug => $label) {
            $args = [
                'page' => 'bressol-forecasting',
                'tab' => $slug,
            ];
            if ($slug === 'results' && $token !== '') {
                $args['token'] = $token;
            }
            $url = add_query_arg($args, admin_url('admin.php'));
            $active = $activeTab === $slug ? ' nav-tab-active' : '';
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . esc_attr($active) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
    }

    private function render_results_tab(string $token): void
    {
        $payload = $this->get_results_payload($token);
        if ($payload === null) {
            echo '<p class="notice notice-warning" style="padding:8px 12px;">';
            echo 'No hay resultados recientes. Ejecuta el forecast.';
            echo '</p>';
            $this->render_run_form();
            return;
        }

        $generatedAt = isset($payload['generated_at_utc']) ? (string) $payload['generated_at_utc'] : '';
        $window = isset($payload['window']) && is_array($payload['window']) ? $payload['window'] : [];
        $fromUtc = isset($window['from_utc']) ? (string) $window['from_utc'] : '';
        $toUtc = isset($window['to_utc']) ? (string) $window['to_utc'] : '';
        $leadTimeDays = isset($payload['lead_time_days']) && is_numeric($payload['lead_time_days'])
            ? (int) $payload['lead_time_days']
            : null;

        echo '<p><strong>Generated at (UTC):</strong> ' . esc_html($generatedAt !== '' ? $generatedAt : '-') . '</p>';
        echo '<p><strong>Window (UTC):</strong> ' . esc_html($fromUtc !== '' ? $fromUtc : '-') . ' → ' . esc_html($toUtc !== '' ? $toUtc : '-') . '</p>';
        if ($leadTimeDays !== null) {
            echo '<p><strong>Lead time (days):</strong> ' . esc_html((string) $leadTimeDays) . '</p>';
        }

        $warnings = isset($payload['warnings']) && is_array($payload['warnings']) ? $payload['warnings'] : [];
        if ($warnings !== []) {
            echo '<div class="notice notice-warning" style="padding:8px 12px;">';
            echo '<ul style="margin:6px 0 0 16px;">';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        $weekly = isset($payload['weekly_forecast']) && is_array($payload['weekly_forecast']) ? $payload['weekly_forecast'] : [];
        $totals = isset($payload['totals']) && is_array($payload['totals']) ? $payload['totals'] : [];
        $plan = isset($payload['purchase_plan']) && is_array($payload['purchase_plan']) ? $payload['purchase_plan'] : [];

        $this->render_weekly_table($weekly);
        $this->render_totals_table($totals);
        $this->render_purchase_plan_table($plan);
    }

    private function render_snapshots_tab(int $eventId): void
    {
        $events = $this->load_recent_events_for_select();
        $notice = isset($_GET['snapshot_notice']) ? sanitize_key((string) wp_unslash($_GET['snapshot_notice'])) : '';
        $snapshotResults = $this->parse_snapshot_results_from_query($_GET);

        echo '<h2>Snapshots</h2>';
        $this->render_snapshot_notice($notice, $snapshotResults);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        wp_nonce_field(self::NONCE_LOAD_SNAPSHOT);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_LOAD_SNAPSHOT) . '" />';
        echo '<table class="form-table" style="max-width:720px;">';
        echo '<tr><th>Event</th><td>';
        echo $this->render_event_select($events, $eventId, 'event_id');
        echo '</td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" class="button">Load snapshot</button></p>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        wp_nonce_field(self::NONCE_GENERATE_POS_SNAPSHOT);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_GENERATE_POS_SNAPSHOT) . '" />';
        echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $eventId) . '" />';
        echo '<button type="submit" class="button">Generate snapshot from POS</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        wp_nonce_field(self::NONCE_BACKFILL_POS_SNAPSHOTS);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_BACKFILL_POS_SNAPSHOTS) . '" />';
        echo '<table class="form-table" style="max-width:720px;">';
        echo '<tr><th>Backfill (months)</th><td><input type="number" min="1" max="24" name="backfill_months" value="3" /></td></tr>';
        echo '<tr><th>Desde (YYYY-MM-DD)</th><td><input type="date" name="backfill_from" value="" /></td></tr>';
        echo '<tr><th>Hasta (YYYY-MM-DD)</th><td><input type="date" name="backfill_to" value="" /></td></tr>';
        echo '</table>';
        echo '<p class="submit"><button type="submit" class="button">Backfill POS snapshots</button></p>';
        echo '</form>';

        if ($eventId <= 0) {
            echo '<p class="description">Selecciona un evento para cargar o crear un snapshot.</p>';
            return;
        }

        $repo = new ForecastSnapshotRepository();
        $snapshot = $repo->get_latest_snapshot_for_event($eventId);
        $lines = [];
        if ($snapshot && !empty($snapshot['id'])) {
            $lines = $repo->get_lines_for_snapshot((int) $snapshot['id']);
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;">';
        wp_nonce_field(self::NONCE_SAVE_SNAPSHOT);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE_SNAPSHOT) . '" />';
        echo '<input type="hidden" name="event_id" value="' . esc_attr((string) $eventId) . '" />';

        echo '<table class="widefat striped" style="max-width:720px;">';
        echo '<thead><tr><th>Product ID</th><th>Qty units</th></tr></thead><tbody>';

        $rowCount = 0;
        foreach ($lines as $line) {
            if ($rowCount >= 200) {
                break;
            }
            $rowCount++;
            $pid = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $qty = isset($line['qty_units']) ? (int) $line['qty_units'] : 0;
            echo '<tr>';
            echo '<td><input type="number" min="1" name="line_product_id[]" value="' . esc_attr((string) $pid) . '" /></td>';
            echo '<td><input type="number" min="0" name="line_qty_units[]" value="' . esc_attr((string) $qty) . '" /></td>';
            echo '</tr>';
        }

        $extraRows = max(5, 15 - $rowCount);
        for ($i = 0; $i < $extraRows; $i++) {
            echo '<tr>';
            echo '<td><input type="number" min="1" name="line_product_id[]" value="" /></td>';
            echo '<td><input type="number" min="0" name="line_qty_units[]" value="" /></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary">Save snapshot</button>';
        echo '<button type="button" class="button" id="bressol-add-snapshot-row">Add row</button>';
        echo '</p>';
        echo '</form>';

        echo '<script>
            (function() {
                var btn = document.getElementById("bressol-add-snapshot-row");
                if (!btn) { return; }
                btn.addEventListener("click", function() {
                    var table = btn.closest("form").querySelector("table tbody");
                    if (!table) { return; }
                    var row = document.createElement("tr");
                    row.innerHTML = "<td><input type=\\"number\\" min=\\"1\\" name=\\"line_product_id[]\\" value=\\"\\" /></td>"
                        + "<td><input type=\\"number\\" min=\\"0\\" name=\\"line_qty_units[]\\" value=\\"\\" /></td>";
                    table.appendChild(row);
                });
            })();
        </script>';
    }

    /** @param array<string, array<int, int>> $weekly */
    private function render_weekly_table(array $weekly): void
    {
        echo '<h2>Weekly forecast</h2>';
        echo '<table class="widefat striped" style="max-width:900px;">';
        echo '<thead><tr><th>Week</th><th>Product ID</th><th>Units</th></tr></thead><tbody>';

        $rows = [];
        foreach ($weekly as $week => $products) {
            foreach ($products as $productId => $units) {
                $rows[] = [$week, (int) $productId, (int) $units];
            }
        }
        usort($rows, static function (array $a, array $b): int {
            if ($a[0] === $b[0]) {
                return $a[1] <=> $b[1];
            }
            return strcmp($a[0], $b[0]);
        });

        if ($rows === []) {
            echo '<tr><td colspan="3">No hay datos.</td></tr>';
        } else {
            $limit = 0;
            foreach ($rows as $row) {
                if ($limit >= 200) {
                    break;
                }
                $limit++;
                echo '<tr>';
                echo '<td>' . esc_html((string) $row[0]) . '</td>';
                echo '<td>' . esc_html((string) $row[1]) . '</td>';
                echo '<td>' . esc_html((string) $row[2]) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    /** @param array<int, int> $totals */
    private function render_totals_table(array $totals): void
    {
        echo '<h2>Totals per product</h2>';
        echo '<table class="widefat striped" style="max-width:640px;">';
        echo '<thead><tr><th>Product ID</th><th>Forecast total</th></tr></thead><tbody>';

        if ($totals === []) {
            echo '<tr><td colspan="2">No hay datos.</td></tr>';
        } else {
            foreach ($totals as $productId => $units) {
                echo '<tr>';
                echo '<td>' . esc_html((string) $productId) . '</td>';
                echo '<td>' . esc_html((string) $units) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    /** @param array<int, array<string, int>> $plan */
    private function render_purchase_plan_table(array $plan): void
    {
        echo '<h2>Purchase plan</h2>';
        echo '<table class="widefat striped" style="max-width:720px;">';
        echo '<thead><tr><th>Product ID</th><th>Forecast</th><th>Sellable</th><th>Buy</th></tr></thead><tbody>';

        if ($plan === []) {
            echo '<tr><td colspan="4">No hay datos.</td></tr>';
        } else {
            foreach ($plan as $productId => $row) {
                $forecast = isset($row['forecast_total']) ? (int) $row['forecast_total'] : 0;
                $sellable = isset($row['sellable']) ? (int) $row['sellable'] : 0;
                $buy = isset($row['buy']) ? (int) $row['buy'] : 0;
                echo '<tr>';
                echo '<td>' . esc_html((string) $productId) . '</td>';
                echo '<td>' . esc_html((string) $forecast) . '</td>';
                echo '<td>' . esc_html((string) $sellable) . '</td>';
                echo '<td>' . esc_html((string) $buy) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    private function redirect_with_notice(string $code, int $eventId = 0, int $productId = 0): void
    {
        $args = [
            'page' => 'bressol-forecasting',
            'tab' => 'selftest',
            'notice' => $code,
        ];
        if ($eventId > 0) {
            $args['event_id'] = $eventId;
        }
        if ($productId > 0) {
            $args['product_id'] = $productId;
        }
        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private function redirect_with_steps(string $encoded, int $eventId, int $productId): void
    {
        $args = [
            'page' => 'bressol-forecasting',
            'tab' => 'selftest',
            'steps' => $encoded,
            'event_id' => $eventId,
            'product_id' => $productId,
        ];
        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private function redirect_to_results(string $token): void
    {
        $args = [
            'page' => 'bressol-forecasting',
            'tab' => 'results',
            'token' => $token,
        ];
        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    /** @return array<int, array<string,mixed>> */
    private function load_recent_events_for_select(): array
    {
        if (!class_exists(EventRepository::class)) {
            return [];
        }

        $repo = new EventRepository();
        $events = $repo->find_by_filters([], 200, 1);
        if (!is_array($events)) {
            return [];
        }

        usort($events, static function (array $a, array $b): int {
            $aStart = isset($a['start_at']) ? strtotime((string) $a['start_at']) : 0;
            $bStart = isset($b['start_at']) ? strtotime((string) $b['start_at']) : 0;
            return $bStart <=> $aStart;
        });

        return array_slice($events, 0, 50);
    }

    /** @param array<string,mixed> $e */
    private function format_event_label(array $e): string
    {
        $title = isset($e['title']) ? (string) $e['title'] : '';
        $loc = isset($e['location_name']) ? (string) $e['location_name'] : '';

        $start = '';
        foreach (['start_at', 'start_at_utc', 'start', 'starts_at'] as $k) {
            if (!empty($e[$k]) && is_string($e[$k])) {
                $start = $e[$k];
                break;
            }
        }

        $date = $start !== '' ? substr($start, 0, 10) : '—';
        $label = $date . ' — ' . ($title !== '' ? $title : '(sin título)');
        if ($loc !== '') {
            $label .= ' (' . $loc . ')';
        }

        return $label;
    }

    private function get_active_tab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'selftest';
        return in_array($tab, ['selftest', 'results', 'snapshots'], true) ? $tab : 'selftest';
    }

    /** @return array<string, mixed>|null */
    private function get_results_payload(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $key = self::RESULTS_TRANSIENT_PREFIX . $token;
        $payload = get_transient($key);
        return is_array($payload) ? $payload : null;
    }

    /** @param array<int, array<string, mixed>> $events */
    private function render_event_select(array $events, int $eventId, string $name): string
    {
        if ($events === []) {
            return '<input type="number" min="1" name="' . esc_attr($name) . '" value="' . esc_attr((string) ($eventId > 0 ? $eventId : '')) . '" required />';
        }

        $html = '<select name="' . esc_attr($name) . '" required style="min-width:420px;">';
        $html .= '<option value="">— Selecciona un evento —</option>';
        foreach ($events as $e) {
            $id = (int) ($e['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = $this->format_event_label($e);
            $selected = $eventId === $id ? ' selected' : '';
            $html .= '<option value="' . esc_attr((string) $id) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    /** @param array<string, mixed> $input
     *  @return array<int, array{product_id:int,qty_units:int}>
     */
    private function parse_snapshot_lines(array $input): array
    {
        $productIds = isset($input['line_product_id']) && is_array($input['line_product_id']) ? $input['line_product_id'] : [];
        $qtyUnits = isset($input['line_qty_units']) && is_array($input['line_qty_units']) ? $input['line_qty_units'] : [];

        $lines = [];
        $count = min(max(count($productIds), count($qtyUnits)), 200);
        for ($i = 0; $i < $count; $i++) {
            $productId = isset($productIds[$i]) ? absint($productIds[$i]) : 0;
            $qtyRaw = isset($qtyUnits[$i]) ? wp_unslash($qtyUnits[$i]) : '';
            $qty = is_numeric($qtyRaw) ? (int) $qtyRaw : -1;
            if ($productId <= 0 || $qty < 0) {
                continue;
            }
            $lines[] = [
                'product_id' => $productId,
                'qty_units' => $qty,
            ];
        }

        return $lines;
    }

    private function render_snapshot_notice(string $notice, array $snapshotResults): void
    {
        $messages = [
            'snapshot_saved' => 'Snapshot guardado.',
            'snapshot_save_failed' => 'No se pudo guardar el snapshot.',
            'snapshot_invalid_event' => 'Evento inválido.',
            'snapshot_pos_ok' => 'Snapshot POS generado.',
            'snapshot_pos_failed' => 'No se pudo generar snapshot POS.',
            'snapshot_backfill_done' => 'Backfill completado.',
        ];

        if ($notice !== '' && isset($messages[$notice])) {
            $type = strpos($notice, 'failed') !== false || $notice === 'snapshot_invalid_event' ? 'notice-error' : 'notice-success';
            echo '<p class="notice ' . esc_attr($type) . '" style="padding:8px 12px;">' . esc_html($messages[$notice]) . '</p>';
        }

        if ($snapshotResults !== []) {
            if (isset($snapshotResults['warnings']) && is_array($snapshotResults['warnings']) && $snapshotResults['warnings'] !== []) {
                echo '<div class="notice notice-warning" style="padding:8px 12px;">';
                echo '<ul style="margin:6px 0 0 16px;">';
                foreach ($snapshotResults['warnings'] as $warning) {
                    echo '<li>' . esc_html((string) $warning) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            if (isset($snapshotResults['backfill']) && is_array($snapshotResults['backfill'])) {
                echo '<h3>Backfill results</h3>';
                echo '<table class="widefat striped" style="max-width:720px;">';
                echo '<thead><tr><th>Event ID</th><th>Status</th><th>Message</th></tr></thead><tbody>';
                foreach ($snapshotResults['backfill'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $eventId = isset($row['event_id']) ? (int) $row['event_id'] : 0;
                    $ok = !empty($row['ok']);
                    $message = isset($row['message']) ? (string) $row['message'] : '';
                    $status = $ok ? 'ok' : 'error';
                    echo '<tr>';
                    echo '<td>' . esc_html((string) $eventId) . '</td>';
                    echo '<td>' . esc_html($status) . '</td>';
                    echo '<td>' . esc_html($message) . '</td>';
                    echo '</tr>';
                    if (isset($row['warnings']) && is_array($row['warnings']) && $row['warnings'] !== []) {
                        echo '<tr><td colspan="3"><ul style="margin:6px 0 0 16px;">';
                        foreach ($row['warnings'] as $warning) {
                            echo '<li>' . esc_html((string) $warning) . '</li>';
                        }
                        echo '</ul></td></tr>';
                    }
                }
                echo '</tbody></table>';
            }
        }
    }

    private function encode_snapshot_result(array $result): string
    {
        $payload = wp_json_encode($result);
        if (!is_string($payload)) {
            return '';
        }

        return base64_encode($payload);
    }

    /** @return array<string, mixed> */
    private function parse_snapshot_results_from_query(array $query): array
    {
        if (empty($query['snapshot_results'])) {
            return [];
        }

        $raw = sanitize_text_field(wp_unslash((string) $query['snapshot_results']));
        if ($raw === '') {
            return [];
        }

        $decoded = base64_decode($raw, true);
        if (!is_string($decoded) || $decoded === '') {
            return [];
        }

        $data = json_decode($decoded, true);
        return is_array($data) ? $data : [];
    }

    /** @return array<string, mixed> */
    private function parse_pos_debug_results_from_query(array $query): array
    {
        if (empty($query['pos_debug_results'])) {
            return [];
        }

        $raw = sanitize_text_field(wp_unslash((string) $query['pos_debug_results']));
        if ($raw === '') {
            return [];
        }

        $decoded = base64_decode($raw, true);
        if (!is_string($decoded) || $decoded === '') {
            return [];
        }

        $data = json_decode($decoded, true);
        return is_array($data) ? $data : [];
    }

    /** @return array{from_utc:string,to_utc:string} */
    private function parse_backfill_range(array $input): array
    {
        $months = isset($input['backfill_months']) ? absint($input['backfill_months']) : 0;
        $from = isset($input['backfill_from']) ? sanitize_text_field(wp_unslash($input['backfill_from'])) : '';
        $to = isset($input['backfill_to']) ? sanitize_text_field(wp_unslash($input['backfill_to'])) : '';

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($months > 0) {
            $fromDate = $now->modify('-' . $months . ' months')->setTime(0, 0, 0);
            return [
                'from_utc' => $fromDate->format('Y-m-d H:i:s'),
                'to_utc' => $now->format('Y-m-d H:i:s'),
            ];
        }

        $fromUtc = $this->normalize_date_only($from);
        $toUtc = $this->normalize_date_only($to, true);
        if ($fromUtc === '' || $toUtc === '') {
            $fallback = $now->modify('-3 months')->setTime(0, 0, 0);
            return [
                'from_utc' => $fallback->format('Y-m-d H:i:s'),
                'to_utc' => $now->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'from_utc' => $fromUtc,
            'to_utc' => $toUtc,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function load_events_for_backfill(string $fromUtc, string $toUtc): array
    {
        if (!class_exists(EventRepository::class)) {
            return [];
        }

        $repo = new EventRepository();
        $events = [];
        $page = 1;
        $limit = 200;

        do {
            $rows = $repo->find_by_filters([
                'date_from' => $fromUtc,
                'date_to' => $toUtc,
            ], $limit, $page);
            if (!is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $events[] = $row;
            }

            $page++;
        } while (count($rows) === $limit && $page <= 10);

        return $events;
    }

    private function normalize_date_only(string $value, bool $endOfDay = false): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value, new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            return '';
        }
        $time = $endOfDay ? '23:59:59' : '00:00:00';
        return $date->format('Y-m-d') . ' ' . $time;
    }

    private function redirect_to_snapshots(int $eventId, string $notice = '', string $payload = ''): void
    {
        $args = [
            'page' => 'bressol-forecasting',
            'tab' => 'snapshots',
        ];
        if ($eventId > 0) {
            $args['event_id'] = $eventId;
        }
        if ($notice !== '') {
            $args['snapshot_notice'] = $notice;
        }
        if ($payload !== '') {
            $args['snapshot_results'] = $payload;
        }
        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private function redirect_with_pos_debug_results(string $payload, int $eventId = 0): void
    {
        $args = [
            'page' => 'bressol-forecasting',
            'tab' => 'selftest',
        ];
        if ($payload !== '') {
            $args['pos_debug_results'] = $payload;
        }
        if ($eventId > 0) {
            $args['event_id'] = $eventId;
        }
        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private function get_capability(): string
    {
        return class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
    }
}
