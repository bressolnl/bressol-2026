<?php
declare(strict_types=1);

namespace Bressol\Modules\Products\Admin;

use Bressol\Modules\Products\Services\ProductsCsvImporter;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductsImportPage
{
    private const PAGE_SLUG = 'bressol_tools_products_import';
    private const PARENT_SLUG = 'bressol_tools';
    private const NONCE_ACTION = 'bressol_products_import';
    private const TMP_DIR = 'bressol-import/tmp';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_bressol_products_import_start', [$this, 'handleStart']);
        add_action('wp_ajax_bressol_products_import_step', [$this, 'handleStep']);
    }

    public function registerMenus(): void
    {
        $capability = $this->get_capability();

        if (!$this->menu_exists(self::PARENT_SLUG)) {
            add_menu_page(
                'Bressol Tools',
                'Bressol Tools',
                $capability,
                self::PARENT_SLUG,
                [$this, 'renderPage'],
                'dashicons-admin-tools',
                58
            );
        }

        add_submenu_page(
            self::PARENT_SLUG,
            'Import Products (CSV)',
            'Import Products (CSV)',
            $capability,
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page !== self::PAGE_SLUG) {
            return;
        }

        $src = plugins_url(
            'src/Modules/Products/Admin/assets/products-import.js',
            dirname(__DIR__, 4) . '/bressol-core.php'
        );

        wp_enqueue_script('bressol-products-import', $src, [], '0.1.0', true);
        wp_localize_script('bressol-products-import', 'bressolProductsImport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }

    public function renderPage(): void
    {
        if (!current_user_can($this->get_capability())) {
            wp_die('No autorizado.');
        }

        echo '<div class="wrap">';
        echo '<h1>Import Products (CSV)</h1>';
        echo '<p class="description">Carga un CSV UTF-8 exportado desde 02_products_template.xlsx.</p>';

        echo '<form id="bressol-products-import-form" enctype="multipart/form-data">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="bressol_products_file">CSV *</label></th>';
        echo '<td><input type="file" id="bressol_products_file" name="csv_file" accept=".csv" required /></td></tr>';

        echo '<tr><th><label for="bressol_products_mode">Mode</label></th>';
        echo '<td><select id="bressol_products_mode" name="mode">';
        echo '<option value="upsert" selected>upsert</option>';
        echo '<option value="create">create</option>';
        echo '</select></td></tr>';

        echo '<tr><th><label for="bressol_products_dry_run">Dry run</label></th>';
        echo '<td><select id="bressol_products_dry_run" name="dry_run">';
        echo '<option value="1" selected>1</option>';
        echo '<option value="0">0</option>';
        echo '</select></td></tr>';

        echo '<tr><th><label for="bressol_products_strict_terms">Strict terms</label></th>';
        echo '<td><select id="bressol_products_strict_terms" name="strict_terms">';
        echo '<option value="1" selected>1</option>';
        echo '<option value="0">0</option>';
        echo '</select><p class="description">Solo aplica a product_cat (product_tag no estricto).</p></td></tr>';

        echo '<tr><th><label for="bressol_products_create_terms">Create missing terms</label></th>';
        echo '<td><select id="bressol_products_create_terms" name="create_missing_terms">';
        echo '<option value="0" selected>0</option>';
        echo '<option value="1">1</option>';
        echo '</select></td></tr>';

        echo '<tr><th><label for="bressol_products_clear_missing">Clear missing</label></th>';
        echo '<td><select id="bressol_products_clear_missing" name="clear_missing">';
        echo '<option value="0" selected>0</option>';
        echo '<option value="1">1</option>';
        echo '</select></td></tr>';

        echo '<tr><th><label for="bressol_products_force_simple">Force simple</label></th>';
        echo '<td><select id="bressol_products_force_simple" name="force_simple">';
        echo '<option value="1" selected>1</option>';
        echo '<option value="0">0</option>';
        echo '</select></td></tr>';

        echo '<tr><th><label for="bressol_products_batch">Batch size</label></th>';
        echo '<td><input type="number" min="10" max="500" value="50" id="bressol_products_batch" name="batch_size" /></td></tr>';

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary" id="bressol-products-import-start">Start Import</button></p>';
        echo '</form>';

        echo '<hr />';
        echo '<h2>Progreso</h2>';
        echo '<p id="bressol-products-import-progress">Sin ejecución.</p>';
        echo '<p><a id="bressol-products-import-report" href="#" target="_blank" style="display:none;">Descargar reporte CSV</a></p>';
        echo '<div id="bressol-products-import-summary"></div>';
        echo '<div id="bressol-products-import-errors"></div>';
        echo '</div>';
    }

    public function handleStart(): void
    {
        if (!current_user_can($this->get_capability())) {
            wp_send_json_error(['message' => 'No autorizado.'], 403);
        }
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(['message' => 'Método inválido.'], 405);
        }
        if (!class_exists('\\WooCommerce')) {
            wp_send_json_error(['message' => 'WooCommerce no disponible.'], 400);
        }

        if (empty($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
            wp_send_json_error(['message' => 'Archivo CSV requerido.'], 422);
        }

        $file = $_FILES['csv_file'];
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(['message' => 'Subida inválida.'], 422);
        }

        $originalName = isset($file['name']) ? (string) $file['name'] : '';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            wp_send_json_error(['message' => 'Solo CSV (UTF-8).'], 422);
        }

        $upload = wp_upload_dir();
        $baseDir = is_array($upload) && !empty($upload['basedir']) ? (string) $upload['basedir'] : '';
        if ($baseDir === '') {
            wp_send_json_error(['message' => 'Uploads no disponibles.'], 500);
        }
        $tmpDir = trailingslashit($baseDir) . self::TMP_DIR;
        wp_mkdir_p($tmpDir);
        $filename = wp_unique_filename($tmpDir, $originalName !== '' ? $originalName : 'products.csv');
        $targetPath = trailingslashit($tmpDir) . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            wp_send_json_error(['message' => 'No se pudo guardar el CSV.'], 500);
        }

        $importer = new ProductsCsvImporter();
        $headerInfo = $importer->read_header($targetPath);
        $headerErrors = $importer->validate_headers($headerInfo['header'], $headerInfo['delimiter']);
        if ($headerErrors !== []) {
            wp_send_json_error(['message' => implode(' ', $headerErrors)], 422);
        }

        $options = $this->parse_options($_POST);
        $reportPath = $importer->init_report_file();
        $reportUrl = $importer->report_path_to_url($reportPath);

        $jobId = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('bressol_', true);
        $payload = [
            'file_path' => $targetPath,
            'report_path' => $reportPath,
            'offset_rows' => 0,
            'stats' => [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
            ],
            'errors_preview' => [],
            'options' => $options,
        ];
        set_transient($this->transient_key($jobId), $payload, HOUR_IN_SECONDS);

        wp_send_json_success([
            'job_id' => $jobId,
            'stats' => $payload['stats'],
            'next_offset' => 0,
            'report_url' => $reportUrl,
        ]);
    }

    public function handleStep(): void
    {
        if (!current_user_can($this->get_capability())) {
            wp_send_json_error(['message' => 'No autorizado.'], 403);
        }
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(['message' => 'Método inválido.'], 405);
        }

        $jobId = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        if ($jobId === '') {
            wp_send_json_error(['message' => 'job_id requerido.'], 422);
        }
        $payload = get_transient($this->transient_key($jobId));
        if (!is_array($payload)) {
            wp_send_json_error(['message' => 'Job expirado o inválido.'], 404);
        }

        $filePath = (string) ($payload['file_path'] ?? '');
        if ($filePath === '' || !is_readable($filePath)) {
            wp_send_json_error(['message' => 'CSV no disponible.'], 404);
        }

        $options = isset($payload['options']) && is_array($payload['options']) ? $payload['options'] : [];
        $batchSize = isset($options['batch_size']) ? max(10, (int) $options['batch_size']) : 50;
        $options['report_path'] = (string) ($payload['report_path'] ?? '');

        $importer = new ProductsCsvImporter();
        $chunk = $importer->import_chunk(
            $filePath,
            (int) ($payload['offset_rows'] ?? 0),
            $batchSize,
            $options
        );

        $stats = isset($payload['stats']) && is_array($payload['stats']) ? $payload['stats'] : [];
        foreach (['processed', 'created', 'updated', 'skipped', 'errors'] as $key) {
            $stats[$key] = (int) ($stats[$key] ?? 0) + (int) ($chunk['stats'][$key] ?? 0);
        }

        $errorsPreview = isset($payload['errors_preview']) && is_array($payload['errors_preview'])
            ? $payload['errors_preview']
            : [];
        foreach (($chunk['errors_preview'] ?? []) as $err) {
            if (count($errorsPreview) >= 20) {
                break;
            }
            $errorsPreview[] = $err;
        }

        $payload['offset_rows'] = (int) $chunk['next_offset'];
        $payload['stats'] = $stats;
        $payload['errors_preview'] = $errorsPreview;
        set_transient($this->transient_key($jobId), $payload, HOUR_IN_SECONDS);

        $reportUrl = $importer->report_path_to_url((string) ($payload['report_path'] ?? ''));
        $done = (bool) $chunk['done'];
        if ($done) {
            delete_transient($this->transient_key($jobId));
        }

        wp_send_json_success([
            'done' => $done,
            'next_offset' => (int) $chunk['next_offset'],
            'stats' => $stats,
            'errors_preview' => $errorsPreview,
            'report_url' => $reportUrl,
        ]);
    }

    private function parse_options(array $input): array
    {
        $mode = isset($input['mode']) ? sanitize_key(wp_unslash($input['mode'])) : 'upsert';
        if (!in_array($mode, ['upsert', 'create'], true)) {
            $mode = 'upsert';
        }
        $batchSize = isset($input['batch_size']) ? (int) $input['batch_size'] : 50;
        $batchSize = max(10, min(500, $batchSize));

        return [
            'mode' => $mode,
            'dry_run' => isset($input['dry_run']) ? (int) $input['dry_run'] === 1 : true,
            'strict_terms' => isset($input['strict_terms']) ? (int) $input['strict_terms'] === 1 : true,
            'create_missing_terms' => isset($input['create_missing_terms']) ? (int) $input['create_missing_terms'] === 1 : false,
            'clear_missing' => isset($input['clear_missing']) ? (int) $input['clear_missing'] === 1 : false,
            'force_simple' => isset($input['force_simple']) ? (int) $input['force_simple'] === 1 : true,
            'batch_size' => $batchSize,
        ];
    }

    private function transient_key(string $jobId): string
    {
        return 'bressol_products_import_' . $jobId;
    }

    private function menu_exists(string $slug): bool
    {
        global $menu;
        if (!is_array($menu)) {
            return false;
        }
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === $slug) {
                return true;
            }
        }
        return false;
    }

    private function get_capability(): string
    {
        return class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
    }
}
