<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Cli;

use Bressol\Modules\SalesAnalytics\Repositories\OrderQuery;
use Bressol\Modules\SalesAnalytics\Services\MetricsExtractor;
use Bressol\Modules\SalesAnalytics\Services\TaxBreakdownService;

if (!defined('ABSPATH')) {
    exit;
}

final class SelfTestCommand
{
    /**
     * Ejecuta selftest de Sales Analytics (sin PII).
     *
     * ## OPTIONS
     *
     * [--from=<date>]
     * : Fecha minima (YYYY-MM-DD)
     *
     * [--to=<date>]
     * : Fecha maxima (YYYY-MM-DD)
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        if (!class_exists('WooCommerce')) {
            \WP_CLI::error('WooCommerce no disponible.');
            return;
        }

        $filters = [
            'date_from' => isset($assocArgs['from']) ? (string) $assocArgs['from'] : '',
            'date_to' => isset($assocArgs['to']) ? (string) $assocArgs['to'] : '',
            'channel' => 'all',
            'market_id' => '',
        ];

        $dashboard = $this->compute_dashboard($filters, 2000);
        if ($dashboard['too_large']) {
            \WP_CLI::warning('Dashboard guardrail activado (rango grande).');
        } else {
            \WP_CLI::log('Dashboard OK. Pedidos: ' . (int) $dashboard['meta']['orders_count']);
        }

        $this->print_orders_csv_preview($filters);
        $this->print_daily_csv_preview($filters);
    }

    /** @param array<string, mixed> $filters
     *  @return array{too_large:bool, meta:array<string, mixed>}
     */
    private function compute_dashboard(array $filters, int $maxOrders): array
    {
        $query = new OrderQuery();
        $page = 1;
        $limit = 200;
        $count = 0;

        do {
            $ids = $query->find_order_ids($filters, $limit, $page);
            if ($ids === []) {
                break;
            }

            $count += count($ids);
            if ($count > $maxOrders) {
                return [
                    'too_large' => true,
                    'meta' => ['orders_count' => $count],
                ];
            }

            $page++;
        } while (true);

        return [
            'too_large' => false,
            'meta' => [
                'orders_count' => $count,
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function print_orders_csv_preview(array $filters): void
    {
        $header = [
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

        \WP_CLI::log('Orders CSV (2 primeras lineas):');
        \WP_CLI::log(implode(',', $header));

        $query = new OrderQuery();
        $ids = $query->find_order_ids($filters, 1, 1);
        if ($ids === []) {
            \WP_CLI::log('No hay pedidos para el rango.');
            return;
        }

        $order = wc_get_order($ids[0]);
        if (!$order instanceof \WC_Order) {
            return;
        }

        $metrics = (new MetricsExtractor())->extract($order);
        $taxBreakdown = (new TaxBreakdownService())->breakdown_for_order($order);
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
            wp_json_encode($taxBreakdown, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        \WP_CLI::log(implode(',', $row));
    }

    /** @param array<string, mixed> $filters */
    private function print_daily_csv_preview(array $filters): void
    {
        $header = [
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

        \WP_CLI::log('Daily CSV (2 primeras lineas):');
        \WP_CLI::log(implode(',', $header));

        $query = new OrderQuery();
        $ids = $query->find_order_ids($filters, 200, 1);
        if ($ids === []) {
            \WP_CLI::log('No hay pedidos para el rango.');
            return;
        }

        $aggregate = [];
        $currency = function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '';
        $taxService = new TaxBreakdownService();
        foreach ($ids as $orderId) {
            $order = wc_get_order($orderId);
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $metrics = (new MetricsExtractor())->extract($order);
            $date = substr($metrics['created_at'], 0, 10);
            if ($date === '') {
                continue;
            }
            $key = $date . '|' . $metrics['channel'] . '|' . $metrics['market_id'];
            if (!isset($aggregate[$key])) {
                $aggregate[$key] = [
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

            $aggregate[$key]['orders_count']++;
            $aggregate[$key]['total_incl_tax_cents'] += $metrics['total_incl_tax_cents'];
            $aggregate[$key]['tax_total_cents'] += $metrics['tax_total_cents'];
            $aggregate[$key]['total_excl_tax_cents'] += $metrics['total_excl_tax_cents'];
            $aggregate[$key]['shipping_incl_tax_cents'] += $metrics['shipping_incl_tax_cents'];
            $aggregate[$key]['discount_incl_tax_cents'] += $metrics['discount_incl_tax_cents'];
            $aggregate[$key]['refunds_incl_tax_cents'] += $metrics['refunds_incl_tax_cents'];
            $aggregate[$key]['market_cost_cents'] += $metrics['market_cost_cents'];
            $aggregate[$key]['net_sales_excl_tax_cents'] += $metrics['net_sales_excl_tax_cents'];
            $aggregate[$key]['profit_estimated_excl_tax_cents'] += $metrics['profit_estimated_excl_tax_cents'];

            $breakdown = $taxService->breakdown_for_order($order);
            foreach ($breakdown as $rate => $data) {
                if (!isset($aggregate[$key]['tax_breakdown'][$rate])) {
                    $aggregate[$key]['tax_breakdown'][$rate] = [
                        'tax_cents' => 0,
                        'taxable_base_cents' => 0,
                    ];
                }
                $aggregate[$key]['tax_breakdown'][$rate]['tax_cents'] += (int) $data['tax_cents'];
                $aggregate[$key]['tax_breakdown'][$rate]['taxable_base_cents'] += (int) $data['taxable_base_cents'];
            }
        }

        ksort($aggregate);
        $first = array_shift($aggregate);
        if (!$first) {
            \WP_CLI::log('No hay agregados para el rango.');
            return;
        }

        $row = [
            $first['date'],
            $first['channel'],
            $first['market_id'],
            $first['market_name'],
            $first['currency'],
            $first['orders_count'],
            $first['total_incl_tax_cents'],
            $first['tax_total_cents'],
            $first['total_excl_tax_cents'],
            $first['shipping_incl_tax_cents'],
            $first['discount_incl_tax_cents'],
            $first['refunds_incl_tax_cents'],
            $first['market_cost_cents'],
            $first['net_sales_excl_tax_cents'],
            $first['profit_estimated_excl_tax_cents'],
            wp_json_encode($first['tax_breakdown'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        \WP_CLI::log(implode(',', $row));
    }
}
