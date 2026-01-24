<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Cli;

use Bressol\Modules\SalesAnalytics\Services\TaxBreakdownService;

if (!defined('ABSPATH')) {
    exit;
}

final class SeedDemoCommand
{
    /**
     * Crea pedidos demo (completed) para Sales Analytics.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Dias hacia atras (default 14)
     *
     * [--pos=<count>]
     * : Numero de pedidos POS (default 30)
     *
     * [--web=<count>]
     * : Numero de pedidos web (default 20)
     *
     * [--refunds=<count>]
     * : Numero de refunds parciales (default 3)
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        if (!function_exists('wc_create_order') || !class_exists('WooCommerce')) {
            \WP_CLI::error('WooCommerce no disponible.');
            return;
        }

        $days = isset($assocArgs['days']) ? max(1, (int) $assocArgs['days']) : 14;
        $posCount = isset($assocArgs['pos']) ? max(0, (int) $assocArgs['pos']) : 30;
        $webCount = isset($assocArgs['web']) ? max(0, (int) $assocArgs['web']) : 20;
        $refundsCount = isset($assocArgs['refunds']) ? max(0, (int) $assocArgs['refunds']) : 3;

        $products = $this->get_or_create_products();
        if ($products === []) {
            \WP_CLI::error('No se pudieron preparar productos.');
            return;
        }

        $orders = [];
        $totalOrders = $posCount + $webCount;
        $suffix = substr(wp_generate_uuid4(), 0, 6);

        for ($i = 1; $i <= $totalOrders; $i++) {
            $isPos = $i <= $posCount;
            $order = wc_create_order();
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $product = $products[array_rand($products)];
            $qty = random_int(1, 3);
            $order->add_product($product, $qty);

            $order->set_billing_first_name('Dev');
            $order->set_billing_last_name('Order');
            $order->set_billing_email('dev+order' . $suffix . '-' . $i . '@example.invalid');
            $order->set_billing_country('ES');

            $date = (new \DateTimeImmutable('now', wp_timezone()))
                ->modify('-' . random_int(0, $days - 1) . ' days');
            $order->set_date_created($date);

            if ($isPos) {
                $market = $this->pick_market($i);
                $order->update_meta_data('_bressol_pos_channel', 'pos');
                $order->update_meta_data('_bressol_pos_market_id', $market['id']);
                $order->update_meta_data('_bressol_pos_market_name', $market['name']);
                $order->update_meta_data('_bressol_pos_market_cost_cents', $market['cost_cents']);
            }

            $order->calculate_totals();
            $order->set_status('completed');
            $order->save();

            $orders[] = $order;
        }

        $refundsCreated = $this->create_refunds($orders, $refundsCount);

        \WP_CLI::log('Pedidos creados: ' . count($orders));
        \WP_CLI::log('Refunds creados: ' . $refundsCreated);

        $example = $orders ? $orders[0] : null;
        if ($example instanceof \WC_Order) {
            $taxBreakdown = (new TaxBreakdownService())->breakdown_for_order($example);
            $json = wp_json_encode($taxBreakdown, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            \WP_CLI::log('Ejemplo tax_breakdown_json: ' . ($json ?: '{}'));
        }
    }

    /** @return \WC_Product[] */
    private function get_or_create_products(): array
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if ($products !== []) {
            return $products;
        }

        $product = new \WC_Product_Simple();
        $product->set_name('Demo Producto');
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_regular_price('19.90');
        $product->set_tax_status('taxable');
        $product->save();

        return [$product];
    }

    /** @return array{id:string,name:string,cost_cents:int} */
    private function pick_market(int $index): array
    {
        $markets = [
            ['id' => 'market-1', 'name' => 'Market 1', 'cost_cents' => 250],
            ['id' => 'market-2', 'name' => 'Market 2', 'cost_cents' => 300],
            ['id' => 'market-3', 'name' => 'Market 3', 'cost_cents' => 200],
        ];

        return $markets[$index % count($markets)];
    }

    /** @param \WC_Order[] $orders */
    private function create_refunds(array $orders, int $count): int
    {
        if ($orders === [] || $count <= 0) {
            return 0;
        }

        $created = 0;
        $max = min($count, count($orders));
        $sample = array_slice($orders, 0, $max);

        foreach ($sample as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $total = (float) $order->get_total();
            if ($total <= 0) {
                continue;
            }

            $amount = round($total * 0.2, 2);
            $refund = wc_create_refund([
                'amount' => $amount,
                'reason' => 'demo refund',
                'order_id' => $order->get_id(),
                'refund_payment' => false,
                'restock_items' => false,
            ]);

            if ($refund instanceof \WC_Order_Refund) {
                $created++;
            }
        }

        return $created;
    }
}
