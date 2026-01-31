<?php
declare(strict_types=1);

namespace Bressol\Modules\Products\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductsCsvImporter
{
    private const REPORT_DIR = 'bressol-import';
    private const REPORT_PREFIX = 'products-import-report-';
    private const ALLOWED_STATUS = ['publish', 'draft', 'private'];
    private const ALLOWED_STOCK_STATUS = ['instock', 'outofstock', 'onbackorder'];
    private const ALLOWED_TAX_STATUS = ['taxable', 'shipping', 'none'];

    /** @return array<int, string> */
    public function validate_headers(array $header, string $delimiter = ''): array
    {
        if ($header === []) {
            return ['CSV sin cabecera válida.'];
        }
        $map = $this->build_header_map($header);
        if (!isset($map['sku']) && !isset($map['_sku'])) {
            $detected = $this->stringify_headers($header);
            $suffix = $delimiter !== '' ? ' Delimitador: ' . $delimiter . '.' : '';
            return ['CSV debe incluir columna "sku". Headers detectados: ' . $detected . '.' . $suffix];
        }
        return [];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{stats:array<string,int>,next_offset:int,done:bool,errors_preview:array<int,array<string,string>>,report_path:string}
     */
    public function import_chunk(string $filePath, int $offsetRows, int $limitRows, array $options): array
    {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $errorsPreview = [];

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return [
                'stats' => $stats,
                'next_offset' => $offsetRows,
                'done' => true,
                'errors_preview' => [['row' => '0', 'sku' => '', 'message' => 'No se pudo abrir el archivo CSV.']],
                'report_path' => (string) ($options['report_path'] ?? ''),
            ];
        }

        $headerLine = fgets($handle);
        if ($headerLine === false) {
            fclose($handle);
            return [
                'stats' => $stats,
                'next_offset' => $offsetRows,
                'done' => true,
                'errors_preview' => [['row' => '0', 'sku' => '', 'message' => 'CSV sin cabecera válida.']],
                'report_path' => (string) ($options['report_path'] ?? ''),
            ];
        }
        $headerInfo = $this->parse_header_line($headerLine);
        $header = $headerInfo['header'];
        $delimiter = $headerInfo['delimiter'];
        $headerMap = $this->build_header_map($header);

        $headerErrors = $this->validate_headers($header, $delimiter);
        if ($headerErrors !== []) {
            fclose($handle);
            return [
                'stats' => $stats,
                'next_offset' => $offsetRows,
                'done' => true,
                'errors_preview' => [['row' => '0', 'sku' => '', 'message' => implode(' ', $headerErrors)]],
                'report_path' => (string) ($options['report_path'] ?? ''),
            ];
        }

        $reportPath = (string) ($options['report_path'] ?? '');
        if ($reportPath === '') {
            $reportPath = $this->init_report_file();
        }
        $reportHandle = $this->open_report_handle($reportPath);
        if (!$reportHandle) {
            fclose($handle);
            return [
                'stats' => $stats,
                'next_offset' => $offsetRows,
                'done' => true,
                'errors_preview' => [['row' => '0', 'sku' => '', 'message' => 'No se pudo crear el reporte.']],
                'report_path' => $reportPath,
            ];
        }

        $rowIndex = 1;
        $dataIndex = 0;
        $processed = 0;
        $done = false;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowIndex++;
            $dataIndex++;

            if ($dataIndex <= $offsetRows) {
                continue;
            }

            if ($limitRows > 0 && $processed >= $limitRows) {
                break;
            }

            if ($this->is_empty_row($row)) {
                continue;
            }

            $processed++;
            $stats['processed']++;

            $result = $this->process_row($row, $headerMap, $rowIndex, $options);
            $this->write_report_row($reportHandle, $result['row'], $result['sku'], $result['action'], $result['status'], $result['message']);

            if ($result['status'] === 'error') {
                $stats['errors']++;
                $stats['skipped']++;
                if (count($errorsPreview) < 20) {
                    $errorsPreview[] = [
                        'row' => (string) $result['row'],
                        'sku' => $result['sku'],
                        'message' => $result['message'],
                    ];
                }
                continue;
            }

            if ($result['action'] === 'create') {
                $stats['created']++;
            } elseif ($result['action'] === 'update') {
                $stats['updated']++;
            } elseif ($result['action'] === 'skip') {
                $stats['skipped']++;
            }
        }

        if (feof($handle)) {
            $done = true;
        }

        fclose($handle);
        fclose($reportHandle);

        return [
            'stats' => $stats,
            'next_offset' => $offsetRows + $processed,
            'done' => $done,
            'errors_preview' => $errorsPreview,
            'report_path' => $reportPath,
        ];
    }

    /**
     * @return array{header:array<int,string>,delimiter:string}
     */
    public function read_header(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return ['header' => [], 'delimiter' => ','];
        }
        $line = fgets($handle);
        fclose($handle);
        if ($line === false) {
            return ['header' => [], 'delimiter' => ','];
        }
        return $this->parse_header_line($line);
    }

    public function init_report_file(): string
    {
        $upload = wp_upload_dir();
        $base = is_array($upload) && !empty($upload['basedir']) ? (string) $upload['basedir'] : '';
        $dir = $base !== '' ? $base . '/' . self::REPORT_DIR : '';
        if ($dir !== '' && !is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
            $dir = sys_get_temp_dir();
        }
        $filename = self::REPORT_PREFIX . gmdate('Ymd-Hi') . '.csv';
        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    }

    public function report_path_to_url(string $path): string
    {
        $upload = wp_upload_dir();
        $base = is_array($upload) && !empty($upload['basedir']) ? (string) $upload['basedir'] : '';
        $baseUrl = is_array($upload) && !empty($upload['baseurl']) ? (string) $upload['baseurl'] : '';
        if ($base === '' || $baseUrl === '') {
            return '';
        }
        $normalizedBase = wp_normalize_path($base);
        $normalizedPath = wp_normalize_path($path);
        if (strpos($normalizedPath, $normalizedBase) !== 0) {
            return '';
        }
        $relative = ltrim(substr($normalizedPath, strlen($normalizedBase)), '/');
        return $baseUrl !== '' ? $baseUrl . '/' . $relative : '';
    }

    /** @return resource|false */
    private function open_report_handle(string $path)
    {
        $exists = file_exists($path);
        $handle = fopen($path, 'ab');
        if (!$handle) {
            return false;
        }
        if (!$exists) {
            fputcsv($handle, ['row', 'sku', 'action', 'status', 'message']);
        }
        return $handle;
    }

    /** @param array<int, string> $row
     *  @param array<string, int> $headerMap
     *  @param array<string, mixed> $options
     *  @return array{row:int,sku:string,action:string,status:string,message:string}
     */
    private function process_row(array $row, array $headerMap, int $rowIndex, array $options): array
    {
        $skuRaw = $this->get_value_any($row, $headerMap, ['sku', '_sku']);
        $skuNormalized = strtolower($this->s($skuRaw));
        if ($this->is_template_row($row, $headerMap, $skuNormalized)) {
            return $this->result($rowIndex, $skuNormalized, 'skip', 'ok', 'template row skipped');
        }

        $sku = sanitize_text_field($this->s($skuRaw));
        if ($sku === '') {
            error_log(sprintf('ProductsCsvImporter: validación falla en fila %d (SKU vacío).', $rowIndex));
            return $this->result($rowIndex, '', 'skip', 'error', 'SKU obligatorio.');
        }

        $mode = (string) ($options['mode'] ?? 'upsert');
        $dryRun = !empty($options['dry_run']);
        $strictTerms = !empty($options['strict_terms']);
        $createMissingTerms = !empty($options['create_missing_terms']);
        $clearMissing = !empty($options['clear_missing']);
        $forceSimple = !empty($options['force_simple']);

        $productId = function_exists('wc_get_product_id_by_sku') ? (int) wc_get_product_id_by_sku($sku) : 0;
        $exists = $productId > 0;
        $action = $exists ? 'update' : 'create';

        if ($mode === 'create' && $exists) {
            return $this->result($rowIndex, $sku, 'skip', 'ok', 'SKU ya existe.');
        }

        $errors = [];
        $title = sanitize_text_field($this->s($this->get_value($row, $headerMap, 'title')));
        if ($title === '') {
            error_log(sprintf('ProductsCsvImporter: validación falla en fila %d (SKU %s, título vacío).', $rowIndex, $sku));
            $errors[] = 'Título obligatorio.';
        }

        $hasIsAlcohol = $this->has_column($headerMap, 'is_alcohol');
        $isAlcohol = $hasIsAlcohol ? $this->normalize_flag($this->get_value($row, $headerMap, 'is_alcohol')) : null;

        $status = $this->s($this->get_value($row, $headerMap, 'status'));
        $status = $status !== '' ? sanitize_key($status) : 'publish';
        if ($status !== '' && !in_array($status, self::ALLOWED_STATUS, true)) {
            $errors[] = 'Status inválido: ' . $status;
        }

        $slug = $this->s($this->get_value($row, $headerMap, 'slug'));
        $slug = $slug !== '' ? sanitize_title($slug) : '';

        $faqRaw = $this->s($this->get_value($row, $headerMap, 'bressol_pdp_faq'));
        $faqNormalized = '';
        if ($faqRaw !== '') {
            $decoded = json_decode($faqRaw, true);
            if (!is_array($decoded)) {
                $errors[] = 'bressol_pdp_faq inválido (JSON).';
            } else {
                $faqNormalized = wp_json_encode($decoded);
            }
        }

        $categories = $this->parse_csv_list($this->s($this->get_value($row, $headerMap, 'categories')));
        $tags = $this->parse_csv_list($this->s($this->get_value($row, $headerMap, 'tags')));
        $termErrors = $this->validate_terms($categories, 'product_cat', $strictTerms, $createMissingTerms, $dryRun);
        $termErrors = array_merge($termErrors, $this->validate_terms($tags, 'product_tag', false, $createMissingTerms, $dryRun));
        $errors = array_merge($errors, $termErrors);

        $hasTaxClass = $this->has_column($headerMap, 'tax_class');
        $taxClassDecision = $this->resolve_tax_class(
            $this->s($this->get_value($row, $headerMap, 'tax_class')),
            $categories,
            $isAlcohol ?? 0,
            $hasTaxClass,
            $errors
        );

        if ($errors !== []) {
            error_log(sprintf('ProductsCsvImporter: fila %d (SKU %s) omitida por validación.', $rowIndex, $sku));
            return $this->result($rowIndex, $sku, 'skip', 'error', implode(' ', $errors));
        }

        if ($dryRun) {
            return $this->result($rowIndex, $sku, $action, 'ok', 'dry-run');
        }

        $postId = $exists ? $productId : 0;
        if (!$exists) {
            $content = $this->get_post_content($row, $headerMap, $clearMissing) ?? '';
            $excerpt = $this->get_post_excerpt($row, $headerMap, $clearMissing) ?? '';
            $postId = wp_insert_post([
                'post_type' => 'product',
                'post_status' => $status !== '' ? $status : 'publish',
                'post_title' => $title,
                'post_name' => $slug !== '' ? $slug : null,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
            ], true);
            if ($postId instanceof \WP_Error) {
                return $this->result($rowIndex, $sku, 'create', 'error', $postId->get_error_message());
            }
            $postId = (int) $postId;
        } else {
            $postUpdate = ['ID' => $postId];
            if ($title !== '') {
                $postUpdate['post_title'] = $title;
            }
            if ($slug !== '') {
                $postUpdate['post_name'] = $slug;
            }
            $content = $this->get_post_content($row, $headerMap, $clearMissing);
            if ($content !== null) {
                $postUpdate['post_content'] = $content;
            }
            $excerpt = $this->get_post_excerpt($row, $headerMap, $clearMissing);
            if ($excerpt !== null) {
                $postUpdate['post_excerpt'] = $excerpt;
            }
            if ($status !== '') {
                $postUpdate['post_status'] = $status;
            }
            $result = wp_update_post($postUpdate, true);
            if ($result instanceof \WP_Error) {
                return $this->result($rowIndex, $sku, 'update', 'error', $result->get_error_message());
            }
        }

        if ($forceSimple) {
            wp_set_object_terms($postId, 'simple', 'product_type', false);
        }

        $this->apply_taxonomies($postId, $headerMap, $categories, $tags, $clearMissing);
        $this->apply_core_meta(
            $postId,
            $headerMap,
            $row,
            $sku,
            $clearMissing,
            $isAlcohol,
            $hasIsAlcohol,
            $taxClassDecision
        );
        $this->apply_custom_meta($postId, $headerMap, $row, $faqRaw, $faqNormalized, $clearMissing);

        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($postId);
        }

        return $this->result($rowIndex, $sku, $action, 'ok', $action === 'create' ? 'created' : 'updated');
    }

    private function apply_taxonomies(int $postId, array $headerMap, array $categories, array $tags, bool $clearMissing): void
    {
        if ($this->has_column($headerMap, 'categories')) {
            if ($categories !== [] || $clearMissing) {
                wp_set_object_terms($postId, $categories, 'product_cat', false);
            }
        }
        if ($this->has_column($headerMap, 'tags')) {
            if ($tags !== [] || $clearMissing) {
                wp_set_object_terms($postId, $tags, 'product_tag', false);
            }
        }
    }

    private function apply_core_meta(
        int $postId,
        array $headerMap,
        array $row,
        string $sku,
        bool $clearMissing,
        ?int $isAlcohol,
        bool $hasIsAlcohol,
        ?array $taxClassDecision
    ): void {
        update_post_meta($postId, '_sku', $sku);

        if ($hasIsAlcohol) {
            $value = $isAlcohol ?? 0;
            update_post_meta($postId, '_bressol_is_alcohol', $value);
            $this->apply_alcohol_attribute($postId, $value);
        }

        $regularKey = $this->has_column($headerMap, '_regular_price') ? '_regular_price' : 'regular_price';
        $saleKey = $this->has_column($headerMap, '_sale_price') ? '_sale_price' : 'sale_price';
        $regularRaw = $this->get_value($row, $headerMap, $regularKey);
        $saleRaw = $this->get_value($row, $headerMap, $saleKey);
        $regular = $this->has_column($headerMap, $regularKey)
            ? $this->parse_decimal($regularRaw)
            : null;
        $sale = $this->has_column($headerMap, $saleKey)
            ? $this->parse_decimal($saleRaw)
            : null;
        $priceTouched = false;

        if ($this->has_column($headerMap, $regularKey)) {
            if ($regularRaw !== '' || $clearMissing) {
                update_post_meta($postId, '_regular_price', $regular !== null ? $regular : '');
                $priceTouched = true;
            }
        }
        if ($this->has_column($headerMap, $saleKey)) {
            if ($saleRaw !== '' || $clearMissing) {
                update_post_meta($postId, '_sale_price', $sale !== null ? $sale : '');
                $priceTouched = true;
            }
        }
        if ($priceTouched) {
            $price = $sale !== null && $sale !== '' ? $sale : $regular;
            update_post_meta($postId, '_price', $price !== null ? $price : '');
            $product = wc_get_product($postId);
            if ($product instanceof \WC_Product) {
                $product->set_regular_price($regular !== null ? $regular : '');
                $product->set_sale_price($sale !== null ? $sale : '');
                $product->set_price($price !== null ? $price : '');
                $product->save();
            }
        }

        $manageKey = $this->has_column($headerMap, '_manage_stock') ? '_manage_stock' : 'manage_stock';
        if ($this->has_column($headerMap, $manageKey)) {
            $manageRaw = $this->get_value($row, $headerMap, $manageKey);
            if ($manageRaw !== '' || $clearMissing) {
                $manage = $this->normalize_yes_no($manageRaw);
                update_post_meta($postId, '_manage_stock', $manage);
            }
        }
        $stockKey = $this->has_column($headerMap, '_stock') ? '_stock' : 'stock';
        if ($this->has_column($headerMap, $stockKey)) {
            $stockRaw = $this->get_value($row, $headerMap, $stockKey);
            if ($stockRaw !== '' || $clearMissing) {
                $stock = $this->parse_int($stockRaw);
                update_post_meta($postId, '_stock', $stock);
            }
        }
        $stockStatusKey = $this->has_column($headerMap, '_stock_status') ? '_stock_status' : 'stock_status';
        if ($this->has_column($headerMap, $stockStatusKey)) {
            $statusRaw = $this->get_value($row, $headerMap, $stockStatusKey);
            if ($statusRaw !== '' || $clearMissing) {
                $status = sanitize_key($statusRaw);
                if ($status !== '' && in_array($status, self::ALLOWED_STOCK_STATUS, true)) {
                    update_post_meta($postId, '_stock_status', $status);
                }
            }
        }

        $weightKey = $this->has_column($headerMap, '_weight') ? '_weight' : 'weight';
        if ($this->has_column($headerMap, $weightKey)) {
            $weightRaw = $this->get_value($row, $headerMap, $weightKey);
            if ($weightRaw !== '' || $clearMissing) {
                update_post_meta($postId, '_weight', $this->parse_decimal($weightRaw) ?? '');
            }
        }
        $lengthKey = $this->has_column($headerMap, '_length') ? '_length' : 'length';
        if ($this->has_column($headerMap, $lengthKey)) {
            $lengthRaw = $this->get_value($row, $headerMap, $lengthKey);
            if ($lengthRaw !== '' || $clearMissing) {
                update_post_meta($postId, '_length', $this->parse_decimal($lengthRaw) ?? '');
            }
        }
        $widthKey = $this->has_column($headerMap, '_width') ? '_width' : 'width';
        if ($this->has_column($headerMap, $widthKey)) {
            $widthRaw = $this->get_value($row, $headerMap, $widthKey);
            if ($widthRaw !== '' || $clearMissing) {
                update_post_meta($postId, '_width', $this->parse_decimal($widthRaw) ?? '');
            }
        }
        $heightKey = $this->has_column($headerMap, '_height') ? '_height' : 'height';
        if ($this->has_column($headerMap, $heightKey)) {
            $heightRaw = $this->get_value($row, $headerMap, $heightKey);
            if ($heightRaw !== '' || $clearMissing) {
                update_post_meta($postId, '_height', $this->parse_decimal($heightRaw) ?? '');
            }
        }

        $taxStatusKey = $this->has_column($headerMap, '_tax_status') ? '_tax_status' : 'tax_status';
        $taxStatusRaw = '';
        if ($this->has_column($headerMap, $taxStatusKey)) {
            $taxStatusRaw = $this->get_value($row, $headerMap, $taxStatusKey);
            if ($taxStatusRaw !== '' || $clearMissing) {
                $taxStatus = sanitize_key($taxStatusRaw);
                if ($taxStatus !== '' && in_array($taxStatus, self::ALLOWED_TAX_STATUS, true)) {
                    update_post_meta($postId, '_tax_status', $taxStatus);
                }
            }
        }
        $taxClassKey = $this->has_column($headerMap, '_tax_class') ? '_tax_class' : 'tax_class';
        if ($taxClassDecision !== null) {
            update_post_meta($postId, '_tax_class', $taxClassDecision['class']);
            if (
                $taxClassDecision['status'] !== null
                && ($taxStatusRaw === '' || !$this->has_column($headerMap, $taxStatusKey))
            ) {
                update_post_meta($postId, '_tax_status', $taxClassDecision['status']);
            }
        }
    }

    private function apply_custom_meta(
        int $postId,
        array $headerMap,
        array $row,
        string $faqRaw,
        string $faqNormalized,
        bool $clearMissing
    ): void {
        if ($this->has_column($headerMap, 'bressol_allergens')) {
            $allergensRaw = $this->get_value($row, $headerMap, 'bressol_allergens');
            if ($allergensRaw !== '' || $clearMissing) {
                if ($allergensRaw === '' && $clearMissing) {
                    delete_post_meta($postId, 'bressol_allergens');
                } else {
                    $allergens = $this->parse_meta_array($allergensRaw);
                    update_post_meta($postId, 'bressol_allergens', $allergens);
                }
            }
        }
        if ($this->has_column($headerMap, 'bressol_may_contain')) {
            $mayContainRaw = $this->get_value($row, $headerMap, 'bressol_may_contain');
            if ($mayContainRaw !== '' || $clearMissing) {
                if ($mayContainRaw === '' && $clearMissing) {
                    delete_post_meta($postId, 'bressol_may_contain');
                } else {
                    $mayContain = $this->parse_meta_array($mayContainRaw);
                    update_post_meta($postId, 'bressol_may_contain', $mayContain);
                }
            }
        }
        if ($this->has_column($headerMap, 'bressol_pdp_faq')) {
            if ($faqRaw !== '' || $clearMissing) {
                update_post_meta($postId, 'bressol_pdp_faq', $faqRaw !== '' ? $faqNormalized : '');
            }
        }

        if ($this->has_column($headerMap, 'moments')) {
            $momentsRaw = $this->s($this->get_value($row, $headerMap, 'moments'));
            if ($momentsRaw !== '' || $clearMissing) {
                if ($momentsRaw === '' && $clearMissing) {
                    delete_post_meta($postId, 'bressol_pack_recommended_moments');
                } else {
                    update_post_meta($postId, 'bressol_pack_recommended_moments', sanitize_text_field($momentsRaw));
                }
            }
        }

        foreach ($headerMap as $key => $index) {
            if (strpos($key, 'bressol_') !== 0) {
                continue;
            }
            if (in_array($key, ['bressol_allergens', 'bressol_may_contain', 'bressol_pdp_faq'], true)) {
                continue;
            }
            $value = isset($row[$index]) ? $this->s($row[$index]) : '';
            if ($value === '' && !$clearMissing) {
                continue;
            }
            if (in_array($key, ['bressol_pdp_intro', 'bressol_pdp_longform', 'bressol_origin_ref'], true)) {
                update_post_meta($postId, $key, wp_kses_post($value));
                continue;
            }
            update_post_meta($postId, $key, sanitize_text_field($value));
        }
    }

    private function get_post_content(array $row, array $headerMap, bool $clearMissing): ?string
    {
        if (!$this->has_column($headerMap, 'description')) {
            return null;
        }
        $value = $this->s($this->get_value($row, $headerMap, 'description'));
        if ($value === '' && !$clearMissing) {
            return null;
        }
        return $value !== '' ? wp_kses_post($value) : '';
    }

    private function get_post_excerpt(array $row, array $headerMap, bool $clearMissing): ?string
    {
        if (!$this->has_column($headerMap, 'short_description')) {
            return null;
        }
        $value = $this->s($this->get_value($row, $headerMap, 'short_description'));
        if ($value === '' && !$clearMissing) {
            return null;
        }
        return $value !== '' ? sanitize_text_field($value) : '';
    }

    private function is_template_row(array $row, array $headerMap, string $skuNormalized): bool
    {
        if (in_array($skuNormalized, ['_sku', 'sku', 'required', ''], true)) {
            return true;
        }

        $title = $this->get_value($row, $headerMap, 'title');
        if ($title !== '' && stripos($title, 'post_title') !== false) {
            return true;
        }

        $markers = [
            'post_title',
            'post_content',
            'product_cat',
            'product_tag',
            'short_description',
            'description',
            'regular_price',
            'sale_price',
        ];
        $hits = 0;
        foreach ($row as $cell) {
            $cellValue = strtolower($this->s($cell));
            if ($cellValue === '') {
                continue;
            }
            foreach ($markers as $marker) {
                if (strpos($cellValue, $marker) !== false) {
                    $hits++;
                    break;
                }
            }
            if ($hits >= 2) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, int> */
    private function build_header_map(array $header): array
    {
        $map = [];
        foreach ($header as $index => $name) {
            $raw = $this->strip_bom((string) $name);
            $key = strtolower(trim($raw));
            if ($key !== '') {
                if (in_array($key, ['sku', '_sku'], true)) {
                    $key = 'sku';
                }
                $map[$key] = $index;
            }
        }
        return $map;
    }

    private function get_value(array $row, array $headerMap, string $key): string
    {
        $key = strtolower($key);
        if (!isset($headerMap[$key])) {
            return '';
        }
        $index = $headerMap[$key];
        return isset($row[$index]) ? $this->s($row[$index]) : '';
    }

    private function get_value_any(array $row, array $headerMap, array $keys): string
    {
        foreach ($keys as $key) {
            if ($this->has_column($headerMap, (string) $key)) {
                return $this->get_value($row, $headerMap, (string) $key);
            }
        }
        return '';
    }

    private function has_column(array $headerMap, string $key): bool
    {
        return isset($headerMap[strtolower($key)]);
    }

    private function parse_csv_list(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $value)));
        $parts = array_map(static function (string $part): string {
            return sanitize_title($part);
        }, $parts);
        return array_values(array_unique(array_filter($parts)));
    }

    private function validate_terms(array $slugs, string $taxonomy, bool $strict, bool $createMissing, bool $dryRun): array
    {
        if ($slugs === []) {
            return [];
        }
        if (!taxonomy_exists($taxonomy)) {
            return ['Taxonomía no disponible: ' . $taxonomy];
        }

        $errors = [];
        foreach ($slugs as $slug) {
            if ($slug === '') {
                continue;
            }
            $term = get_term_by('slug', $slug, $taxonomy);
            if ($term && !is_wp_error($term)) {
                continue;
            }
            if ($createMissing) {
                if (!$dryRun) {
                    $created = wp_insert_term($slug, $taxonomy, ['slug' => $slug]);
                    if (is_wp_error($created)) {
                        $errors[] = 'No se pudo crear término: ' . $slug;
                    }
                }
                continue;
            }
            if ($strict) {
                $errors[] = 'Término inexistente: ' . $taxonomy . ':' . $slug;
            }
        }
        return $errors;
    }

    private function parse_meta_array(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $value)));
        $parts = array_map('sanitize_key', $parts);
        return array_values(array_unique(array_filter($parts)));
    }

    private function parse_decimal(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $value = str_replace(',', '.', $value);
        if (!is_numeric($value)) {
            return null;
        }
        return (string) $value;
    }

    private function parse_int(string $value): int
    {
        if ($value === '') {
            return 0;
        }
        return (int) $value;
    }

    private function normalize_yes_no(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'yes', 'true', 'on'], true) ? 'yes' : 'no';
    }

    private function normalize_flag(string $value): int
    {
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'yes', 'true', 'on'], true) ? 1 : 0;
    }

    private function resolve_tax_class(
        string $value,
        array $categories,
        int $isAlcohol,
        bool $hasTaxClass,
        array &$errors
    ): ?array {
        if (!$hasTaxClass) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return [
                'class' => $this->default_tax_class($categories, $isAlcohol),
                'status' => null,
            ];
        }

        $normalized = str_replace(' ', '-', $normalized);
        if (in_array($normalized, ['standard', 'standard-rate'], true)) {
            return ['class' => '', 'status' => null];
        }
        if (in_array($normalized, ['reduced', 'reduced-rate'], true)) {
            return ['class' => 'reduced-rate', 'status' => null];
        }
        if (in_array($normalized, ['zero', 'zero-rate'], true)) {
            return ['class' => 'zero-rate', 'status' => null];
        }
        if ($normalized === 'none') {
            return ['class' => '', 'status' => 'none'];
        }

        $errors[] = 'tax_class inválido: ' . $normalized;
        return null;
    }

    private function default_tax_class(array $categories, int $isAlcohol): string
    {
        if ($this->has_packaging_category($categories)) {
            return '';
        }
        if ($isAlcohol === 1) {
            return '';
        }
        return 'reduced-rate';
    }

    private function has_packaging_category(array $categories): bool
    {
        $markers = ['packaging', 'accessory', 'accessories', 'accesorio', 'accesorios'];
        foreach ($categories as $slug) {
            foreach ($markers as $marker) {
                if (strpos($slug, $marker) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    private function apply_alcohol_attribute(int $postId, int $isAlcohol): void
    {
        $taxonomy = 'pa_alcohol';
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        $terms = $this->resolve_alcohol_terms($taxonomy);
        if ($terms === []) {
            return;
        }

        $termSlug = $isAlcohol === 1 ? $terms['yes'] : $terms['no'];
        if ($termSlug === '') {
            return;
        }

        wp_set_object_terms($postId, [$termSlug], $taxonomy, false);
        $this->ensure_product_attribute_meta($postId, $taxonomy);
    }

    private function resolve_alcohol_terms(string $taxonomy): array
    {
        $hasJa = term_exists('ja', $taxonomy);
        $hasNee = term_exists('nee', $taxonomy);
        if ($hasJa && $hasNee) {
            return ['yes' => 'ja', 'no' => 'nee'];
        }

        $hasYes = term_exists('yes', $taxonomy);
        $hasNo = term_exists('no', $taxonomy);
        if ($hasYes && $hasNo) {
            return ['yes' => 'yes', 'no' => 'no'];
        }

        $createdYes = term_exists('yes', $taxonomy) ? 'yes' : '';
        $createdNo = term_exists('no', $taxonomy) ? 'no' : '';
        if ($createdYes === '') {
            $created = wp_insert_term('Yes', $taxonomy, ['slug' => 'yes']);
            if (!is_wp_error($created)) {
                $createdYes = 'yes';
            }
        }
        if ($createdNo === '') {
            $created = wp_insert_term('No', $taxonomy, ['slug' => 'no']);
            if (!is_wp_error($created)) {
                $createdNo = 'no';
            }
        }

        if ($createdYes !== '' && $createdNo !== '') {
            return ['yes' => $createdYes, 'no' => $createdNo];
        }
        return [];
    }

    private function ensure_product_attribute_meta(int $postId, string $taxonomy): void
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($postId);
        if (!$product instanceof \WC_Product) {
            return;
        }

        $attributes = $product->get_attributes();
        if (isset($attributes[$taxonomy])) {
            return;
        }

        $attribute = new \WC_Product_Attribute();
        $attribute->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
        $attribute->set_name($taxonomy);
        $attribute->set_visible(true);
        $attribute->set_variation(false);
        $attributes[$taxonomy] = $attribute;
        $product->set_attributes($attributes);
        $product->save();
    }

    private function strip_bom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    private function stringify_headers(array $header): string
    {
        $clean = [];
        foreach ($header as $name) {
            $value = $this->strip_bom((string) $name);
            $value = trim($value);
            if ($value !== '') {
                $clean[] = $value;
            }
        }
        return $clean !== [] ? implode(', ', $clean) : '(vacío)';
    }

    private function parse_header_line(string $line): array
    {
        $delimiter = $this->detect_delimiter($line);
        $header = str_getcsv($line, $delimiter);
        $map = $this->build_header_map($header);

        if (!isset($map['sku']) && $delimiter !== ';') {
            $altHeader = str_getcsv($line, ';');
            $altMap = $this->build_header_map($altHeader);
            if (isset($altMap['sku'])) {
                return ['header' => $altHeader, 'delimiter' => ';'];
            }
        }

        if (!isset($map['sku']) && $delimiter !== ',') {
            $altHeader = str_getcsv($line, ',');
            $altMap = $this->build_header_map($altHeader);
            if (isset($altMap['sku'])) {
                return ['header' => $altHeader, 'delimiter' => ','];
            }
        }

        return ['header' => $header, 'delimiter' => $delimiter];
    }

    private function detect_delimiter(string $line): string
    {
        $semicolon = substr_count($line, ';');
        $comma = substr_count($line, ',');
        if ($semicolon > 0 && $comma === 0) {
            return ';';
        }
        if ($semicolon > $comma) {
            return ';';
        }
        if ($comma > 0) {
            return ',';
        }
        return ',';
    }

    private function is_empty_row(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function result(int $row, string $sku, string $action, string $status, string $message): array
    {
        return [
            'row' => $row,
            'sku' => $sku,
            'action' => $action,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function s($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return trim((string) $value);
        }
        return '';
    }

    private function write_report_row($handle, int $row, string $sku, string $action, string $status, string $message): void
    {
        fputcsv($handle, [$row, $sku, $action, $status, $message]);
    }
}

