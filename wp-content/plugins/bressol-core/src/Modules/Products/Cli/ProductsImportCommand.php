<?php
declare(strict_types=1);

namespace Bressol\Modules\Products\Cli;

use Bressol\Modules\Products\Services\ProductsCsvImporter;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductsImportCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        if (!class_exists('\\WooCommerce')) {
            \WP_CLI::error('WooCommerce no disponible.');
        }

        $file = isset($assocArgs['file']) ? (string) $assocArgs['file'] : '';
        if ($file === '') {
            \WP_CLI::error('Missing --file=PATH.');
        }
        if (!is_readable($file)) {
            \WP_CLI::error('Archivo no legible: ' . $file);
        }

        $format = isset($assocArgs['format']) ? strtolower((string) $assocArgs['format']) : 'csv';
        if ($format !== 'csv') {
            \WP_CLI::error('Solo se soporta CSV. Exporta el XLSX a CSV antes de importar.');
        }

        $mode = isset($assocArgs['mode']) ? strtolower((string) $assocArgs['mode']) : 'upsert';
        if (!in_array($mode, ['upsert', 'create'], true)) {
            \WP_CLI::error('Invalid --mode (use upsert|create).');
        }

        $dryRun = $this->to_bool($assocArgs['dry-run'] ?? '0');
        $strictTerms = $this->to_bool($assocArgs['strict-terms'] ?? '1');
        $createMissingTerms = $this->to_bool($assocArgs['create-missing-terms'] ?? '0');
        $clearMissing = $this->to_bool($assocArgs['clear-missing'] ?? '0');
        $forceSimple = $this->to_bool($assocArgs['force-simple'] ?? '1');
        $limit = isset($assocArgs['limit']) ? max(0, (int) $assocArgs['limit']) : 0;
        $offset = isset($assocArgs['offset']) ? max(0, (int) $assocArgs['offset']) : 0;

        $importer = new ProductsCsvImporter();
        $reportPath = $importer->init_report_file();

        $options = [
            'mode' => $mode,
            'dry_run' => $dryRun,
            'strict_terms' => $strictTerms,
            'create_missing_terms' => $createMissingTerms,
            'clear_missing' => $clearMissing,
            'force_simple' => $forceSimple,
            'report_path' => $reportPath,
        ];

        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $currentOffset = $offset;
        $done = false;

        while (!$done) {
            if ($limit > 0 && $stats['processed'] >= $limit) {
                break;
            }

            $chunkLimit = $limit > 0 ? min(200, $limit - $stats['processed']) : 200;
            $chunk = $importer->import_chunk($file, $currentOffset, $chunkLimit, $options);
            $currentOffset = (int) $chunk['next_offset'];
            $done = (bool) $chunk['done'];

            foreach (['processed', 'created', 'updated', 'skipped', 'errors'] as $key) {
                $stats[$key] += (int) ($chunk['stats'][$key] ?? 0);
            }
        }

        \WP_CLI::log('Reporte: ' . $reportPath);
        \WP_CLI::success(sprintf(
            'Procesadas=%d | creadas=%d | actualizadas=%d | omitidas=%d | errores=%d',
            $stats['processed'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['errors']
        ));
    }

    private function to_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'yes', 'true', 'on'], true);
    }
}
