<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Services;

use Bressol\Modules\SalesAnalytics\Repositories\OrderQuery;

if (!defined('ABSPATH')) {
    exit;
}

final class ExportService
{
    private Settings $settings;
    private Capabilities $capabilities;
    private OrderQuery $orderQuery;
    private MetricsExtractor $metricsExtractor;
    private TaxBreakdownService $taxBreakdownService;
    private CacheService $cacheService;
    private AuditLogger $auditLogger;

    public function __construct(Settings $settings, Capabilities $capabilities, ?CacheService $cacheService = null)
    {
        $this->settings = $settings;
        $this->capabilities = $capabilities;
        $this->orderQuery = new OrderQuery();
        $this->metricsExtractor = new MetricsExtractor();
        $this->taxBreakdownService = new TaxBreakdownService();
        $this->cacheService = $cacheService ?? new CacheService();
        $this->auditLogger = new AuditLogger();
    }

    /** @param array<string, mixed> $filters */
    public function stream_orders_csv(array $filters, bool $includePii = false): void
    {
        $includePii = $this->should_include_pii($includePii);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->build_orders_filename() . '"');

        $output = fopen('php://output', 'w');
        if (!$output) {
            wp_die('No se pudo abrir el export.');
        }

        fputcsv($output, $this->get_orders_header($includePii));

        $page = 1;
        $limit = 200;
        $rowsCount = 0;
        do {
            $orderIds = $this->orderQuery->find_order_ids($filters, $limit, $page);
            if ($orderIds === []) {
                break;
            }

            foreach ($orderIds as $orderId) {
                $order = wc_get_order($orderId);
                if (!$order instanceof \WC_Order) {
                    continue;
                }

                $metrics = $this->metricsExtractor->extract($order);
                $taxBreakdown = $this->taxBreakdownService->breakdown_for_order($order);
                fputcsv($output, $this->map_orders_row($order, $metrics, $taxBreakdown, $includePii));
                $rowsCount++;
            }

            $page++;
        } while (true);

        fclose($output);

        $this->auditLogger->log('sales_export_orders_generated', [
            'export_type' => 'orders',
            'filters' => $filters,
            'include_pii' => $includePii,
            'result' => 'success',
            'rows_count' => $rowsCount,
            'request_uri' => $this->get_request_uri(),
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function stream_daily_market_csv(array $filters): void
    {
        $this->guardrail_date_range($filters);

        $cached = $this->cacheService->get('daily_agg', $filters);
        if (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) {
            $rowsCount = $this->stream_daily_csv_from_cache($cached);
            $this->auditLogger->log('sales_export_daily_generated', [
                'export_type' => 'daily',
                'filters' => $filters,
                'include_pii' => false,
                'result' => 'success',
                'rows_count' => $rowsCount,
                'request_uri' => $this->get_request_uri(),
            ]);
            return;
        }

        $aggregates = [];
        $totalOrders = 0;
        $maxOrders = 5000;
        $page = 1;
        $limit = 200;
        $currency = $this->get_site_currency();

        do {
            $orderIds = $this->orderQuery->find_order_ids($filters, $limit, $page);
            if ($orderIds === []) {
                break;
            }

            foreach ($orderIds as $orderId) {
                $order = wc_get_order($orderId);
                if (!$order instanceof \WC_Order) {
                    continue;
                }

                $metrics = $this->metricsExtractor->extract($order);
                $taxBreakdown = $this->taxBreakdownService->breakdown_for_order($order);
                $date = $this->extract_date($metrics['created_at']);
                if ($date === '') {
                    continue;
                }

                $key = $date . '|' . $metrics['channel'] . '|' . $metrics['market_id'];
                if (!isset($aggregates[$key])) {
                    $aggregates[$key] = $this->init_daily_bucket($date, $metrics, $currency);
                }

                $aggregates[$key]['orders_count']++;
                $aggregates[$key]['total_incl_tax_cents'] += $metrics['total_incl_tax_cents'];
                $aggregates[$key]['tax_total_cents'] += $metrics['tax_total_cents'];
                $aggregates[$key]['total_excl_tax_cents'] += $metrics['total_excl_tax_cents'];
                $aggregates[$key]['shipping_incl_tax_cents'] += $metrics['shipping_incl_tax_cents'];
                $aggregates[$key]['discount_incl_tax_cents'] += $metrics['discount_incl_tax_cents'];
                $aggregates[$key]['refunds_incl_tax_cents'] += $metrics['refunds_incl_tax_cents'];
                $aggregates[$key]['market_cost_cents'] += $metrics['market_cost_cents'];
                $aggregates[$key]['net_sales_excl_tax_cents'] += $metrics['net_sales_excl_tax_cents'];
                $aggregates[$key]['profit_estimated_excl_tax_cents'] += $metrics['profit_estimated_excl_tax_cents'];
                $this->merge_breakdown($aggregates[$key]['tax_breakdown'], $taxBreakdown);

                $totalOrders++;
                if ($totalOrders > $maxOrders) {
                    wp_die('Rango demasiado grande. Acota fechas o usa un rango menor.');
                }
            }

            $page++;
        } while (true);

        $payload = [
            'rows' => $aggregates,
            'meta' => [
                'computed_at' => current_time('mysql'),
                'orders_count' => $totalOrders,
            ],
        ];
        $this->cacheService->set('daily_agg', $filters, $payload, 60 * MINUTE_IN_SECONDS);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->build_daily_filename() . '"');

        $output = fopen('php://output', 'w');
        if (!$output) {
            wp_die('No se pudo abrir el export.');
        }

        fputcsv($output, $this->get_daily_header());
        ksort($aggregates);
        foreach ($aggregates as $row) {
            fputcsv($output, $this->map_daily_row($row));
        }

        fclose($output);

        $this->auditLogger->log('sales_export_daily_generated', [
            'export_type' => 'daily',
            'filters' => $filters,
            'include_pii' => false,
            'result' => 'success',
            'rows_count' => count($aggregates),
            'request_uri' => $this->get_request_uri(),
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function stream_discrepancies_csv(array $filters): void
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->build_discrepancies_filename() . '"');

        $output = fopen('php://output', 'w');
        if (!$output) {
            wp_die('No se pudo abrir el export.');
        }

        fputcsv($output, ['order_id', 'diff_tax_cents']);
        $rowsCount = 0;

        $page = 1;
        $limit = 200;
        do {
            $orderIds = $this->orderQuery->find_order_ids($filters, $limit, $page);
            if ($orderIds === []) {
                break;
            }

            foreach ($orderIds as $orderId) {
                $order = wc_get_order($orderId);
                if (!$order instanceof \WC_Order) {
                    continue;
                }

                $breakdown = $this->taxBreakdownService->breakdown_for_order($order);
                $validation = $this->taxBreakdownService->validate_order($order, $breakdown);
                if (!$validation['ok']) {
                    fputcsv($output, [
                        (int) $order->get_id(),
                        (int) $validation['diff_tax_cents'],
                    ]);
                    $rowsCount++;
                }
            }

            $page++;
        } while (true);

        fclose($output);

        $this->auditLogger->log('sales_export_discrepancies_generated', [
            'export_type' => 'discrepancies',
            'filters' => $filters,
            'include_pii' => false,
            'result' => 'success',
            'rows_count' => $rowsCount,
            'request_uri' => $this->get_request_uri(),
        ]);
    }

    private function build_orders_filename(): string
    {
        return 'bressol-sales-orders-' . gmdate('Ymd-Hi') . '.csv';
    }

    private function build_daily_filename(): string
    {
        return 'bressol-sales-daily-' . gmdate('Ymd-Hi') . '.csv';
    }

    private function build_discrepancies_filename(): string
    {
        return 'bressol-sales-discrepancies-' . gmdate('Ymd-Hi') . '.csv';
    }

    /** @return string[] */
    private function get_orders_header(bool $includePii): array
    {
        $headers = [
            'order_id',
            'order_number',
            'created_at',
            'status',
            'channel',
            'market_id',
            'market_name',
            'currency',
            'total_incl_tax_cents',
            'tax_total_cents',
            'total_excl_tax_cents',
            'shipping_incl_tax_cents',
            'discount_incl_tax_cents',
            'refunds_incl_tax_cents',
            'market_cost_cents',
            'net_sales_excl_tax_cents',
            'profit_estimated_excl_tax_cents',
            'tax_breakdown_json',
        ];

        if ($includePii) {
            $headers[] = 'billing_email';
            $headers[] = 'billing_first_name';
            $headers[] = 'billing_last_name';
            $headers[] = 'billing_country';
        }

        return $headers;
    }

    /** @return string[] */
    private function get_daily_header(): array
    {
        return [
            'date',
            'channel',
            'market_id',
            'market_name',
            'currency',
            'orders_count',
            'total_incl_tax_cents',
            'tax_total_cents',
            'total_excl_tax_cents',
            'shipping_incl_tax_cents',
            'discount_incl_tax_cents',
            'refunds_incl_tax_cents',
            'market_cost_cents',
            'net_sales_excl_tax_cents',
            'profit_estimated_excl_tax_cents',
            'tax_breakdown_json',
        ];
    }

    /** @param array<string, mixed> $metrics
     *  @param array<string, array{tax_cents:int, taxable_base_cents:int}> $taxBreakdown
     *  @return array<int, string|int>
     */
    private function map_orders_row(\WC_Order $order, array $metrics, array $taxBreakdown, bool $includePii): array
    {
        $row = [
            $metrics['order_id'],
            $metrics['order_number'],
            $metrics['created_at'],
            $metrics['status'],
            $metrics['channel'],
            $metrics['market_id'],
            $metrics['market_name'],
            $metrics['currency'],
            $metrics['total_incl_tax_cents'],
            $metrics['tax_total_cents'],
            $metrics['total_excl_tax_cents'],
            $metrics['shipping_incl_tax_cents'],
            $metrics['discount_incl_tax_cents'],
            $metrics['refunds_incl_tax_cents'],
            $metrics['market_cost_cents'],
            $metrics['net_sales_excl_tax_cents'],
            $metrics['profit_estimated_excl_tax_cents'],
            $this->format_tax_breakdown_json($taxBreakdown),
        ];

        if ($includePii) {
            $row[] = (string) $order->get_billing_email();
            $row[] = (string) $order->get_billing_first_name();
            $row[] = (string) $order->get_billing_last_name();
            $row[] = (string) $order->get_billing_country();
        }

        return $row;
    }

    /** @param array<string, mixed> $row
     *  @return array<int, string|int>
     */
    private function map_daily_row(array $row): array
    {
        return [
            $row['date'],
            $row['channel'],
            $row['market_id'],
            $row['market_name'],
            $row['currency'],
            $row['orders_count'],
            $row['total_incl_tax_cents'],
            $row['tax_total_cents'],
            $row['total_excl_tax_cents'],
            $row['shipping_incl_tax_cents'],
            $row['discount_incl_tax_cents'],
            $row['refunds_incl_tax_cents'],
            $row['market_cost_cents'],
            $row['net_sales_excl_tax_cents'],
            $row['profit_estimated_excl_tax_cents'],
            $this->format_tax_breakdown_json($row['tax_breakdown']),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function guardrail_date_range(array $filters): void
    {
        $from = isset($filters['date_from']) ? (string) $filters['date_from'] : '';
        $to = isset($filters['date_to']) ? (string) $filters['date_to'] : '';
        if ($from === '' || $to === '') {
            return;
        }

        $fromDate = \DateTimeImmutable::createFromFormat('Y-m-d', $from);
        $toDate = \DateTimeImmutable::createFromFormat('Y-m-d', $to);
        if (!$fromDate || !$toDate) {
            return;
        }

        if ($fromDate > $toDate) {
            wp_die('Rango inválido. Revisa las fechas seleccionadas.');
        }

        $days = (int) $fromDate->diff($toDate)->days;
        if ($days > 60) {
            wp_die('Rango demasiado grande. Acota fechas o usa un rango menor.');
        }
    }

    /** @param array<string, mixed> $metrics
     *  @return array<string, mixed>
     */
    private function init_daily_bucket(string $date, array $metrics, string $currency): array
    {
        return [
            'date' => $date,
            'channel' => $metrics['channel'],
            'market_id' => $metrics['market_id'],
            'market_name' => $metrics['market_name'],
            'currency' => $currency,
            'orders_count' => 0,
            'total_incl_tax_cents' => 0,
            'tax_total_cents' => 0,
            'total_excl_tax_cents' => 0,
            'shipping_incl_tax_cents' => 0,
            'discount_incl_tax_cents' => 0,
            'refunds_incl_tax_cents' => 0,
            'market_cost_cents' => 0,
            'net_sales_excl_tax_cents' => 0,
            'profit_estimated_excl_tax_cents' => 0,
            'tax_breakdown' => [],
        ];
    }

    private function extract_date(string $createdAt): string
    {
        if ($createdAt === '') {
            return '';
        }

        return substr($createdAt, 0, 10);
    }

    private function get_site_currency(): string
    {
        if (function_exists('get_woocommerce_currency')) {
            return (string) get_woocommerce_currency();
        }

        $currency = get_option('woocommerce_currency');
        return is_string($currency) ? $currency : '';
    }

    /** @param array<string, mixed> $payload */
    private function stream_daily_csv_from_cache(array $payload): int
    {
        $rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
        $rowsCount = 0;

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->build_daily_filename() . '"');

        $output = fopen('php://output', 'w');
        if (!$output) {
            wp_die('No se pudo abrir el export.');
        }

        fputcsv($output, $this->get_daily_header());
        ksort($rows);
        foreach ($rows as $row) {
            fputcsv($output, $this->map_daily_row($row));
            $rowsCount++;
        }

        fclose($output);

        return $rowsCount;
    }

    /** @param array<string, array{tax_cents:int, taxable_base_cents:int}> $target
     *  @param array<string, array{tax_cents:int, taxable_base_cents:int}> $source
     */
    private function merge_breakdown(array &$target, array $source): void
    {
        foreach ($source as $rate => $data) {
            if (!isset($target[$rate])) {
                $target[$rate] = [
                    'tax_cents' => 0,
                    'taxable_base_cents' => 0,
                ];
            }

            $target[$rate]['tax_cents'] += (int) $data['tax_cents'];
            $target[$rate]['taxable_base_cents'] += (int) $data['taxable_base_cents'];
        }
    }

    /** @param array<string, array{tax_cents:int, taxable_base_cents:int}> $breakdown */
    private function format_tax_breakdown_json(array $breakdown): string
    {
        if ($breakdown === []) {
            return '';
        }

        ksort($breakdown);
        $json = wp_json_encode($breakdown, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }

    private function should_include_pii(bool $requested): bool
    {
        if (!$requested) {
            return false;
        }

        if (!$this->settings->is_pii_export_enabled()) {
            return false;
        }

        return $this->capabilities->current_user_can_export_pii();
    }

    private function get_request_uri(): string
    {
        return isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    }
}
