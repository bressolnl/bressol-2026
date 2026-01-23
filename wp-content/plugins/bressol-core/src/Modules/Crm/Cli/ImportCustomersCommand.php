<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Cli;

use Bressol\Modules\Crm\Services\ImportCustomersService;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportCustomersCommand
{
    private ImportCustomersService $importer;

    public function __construct(ImportCustomersService $importer)
    {
        $this->importer = $importer;
    }

    /**
     * Importa clientes desde pedidos existentes de WooCommerce.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Estados separados por coma. Default: completed,processing
     *
     * [--after=<date>]
     * : Fecha mínima (YYYY-MM-DD)
     *
     * [--before=<date>]
     * : Fecha máxima (YYYY-MM-DD)
     *
     * [--limit=<number>]
     * : Límite de pedidos (default 200)
     *
     * [--offset=<number>]
     * : Offset de pedidos (default 0)
     *
     * [--dry-run]
     * : No escribe en BD
     *
     * [--with-points]
     * : También asigna puntos (default false)
     *
     * [--verbose]
     * : Muestra información sensible (emails)
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $results = $this->importer->run([
            'status' => $assocArgs['status'] ?? null,
            'after' => $assocArgs['after'] ?? null,
            'before' => $assocArgs['before'] ?? null,
            'limit' => $assocArgs['limit'] ?? null,
            'offset' => $assocArgs['offset'] ?? null,
            'dry_run' => isset($assocArgs['dry-run']),
            'with_points' => isset($assocArgs['with-points']),
            'verbose' => isset($assocArgs['verbose']),
            'logger' => static function (string $message, string $level) use ($assocArgs): void {
                if ($level === 'error') {
                    \WP_CLI::warning($message);
                    return;
                }

                \WP_CLI::log($message);
            },
        ]);

        if (!empty($results['error'])) {
            \WP_CLI::error((string) $results['error']);
            return;
        }

        $totals = $results['totals'] ?? [];
        \WP_CLI::log('Resumen:');
        \WP_CLI::log(' - Pedidos procesados: ' . (int) ($totals['orders_processed'] ?? 0));
        \WP_CLI::log(' - Clientes creados: ' . (int) ($totals['customers_created'] ?? 0));
        \WP_CLI::log(' - Clientes actualizados: ' . (int) ($totals['customers_updated'] ?? 0));
        \WP_CLI::log(' - Omitidos: ' . (int) ($totals['skipped'] ?? 0));
        \WP_CLI::log(' - Errores: ' . (int) ($totals['errors'] ?? 0));
    }
}
