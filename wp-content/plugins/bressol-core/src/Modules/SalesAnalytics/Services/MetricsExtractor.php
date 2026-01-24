<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class MetricsExtractor
{
    /** @return array<string, int|string> */
    public function extract(\WC_Order $order): array
    {
        $channel = $this->resolve_channel($order);
        $createdAt = $this->format_date($order->get_date_created());
        $totalInclTax = $this->to_cents((float) $order->get_total());
        $taxTotal = $this->to_cents((float) $order->get_total_tax());
        $totalExclTax = $totalInclTax - $taxTotal;
        $shippingExcl = $this->to_cents((float) $order->get_shipping_total());
        $shippingTax = $this->to_cents((float) $order->get_shipping_tax());
        $shippingIncl = $shippingExcl + $shippingTax;
        $discountExcl = $this->to_cents((float) $order->get_discount_total());
        $discountTax = $this->to_cents((float) $order->get_discount_tax());
        $discountIncl = $discountExcl + $discountTax;
        $refundsIncl = $this->to_cents((float) $order->get_total_refunded());
        $marketCost = $channel === 'pos'
            ? (int) $order->get_meta('_bressol_pos_market_cost_cents')
            : 0;
        $netSalesExcl = $totalExclTax - $shippingExcl;
        $profitEstimated = $netSalesExcl - $marketCost;

        return [
            'order_id' => (int) $order->get_id(),
            'order_number' => (string) $order->get_order_number(),
            'created_at' => $createdAt,
            'status' => (string) $order->get_status(),
            'channel' => $channel,
            'market_id' => $channel === 'pos' ? (string) $order->get_meta('_bressol_pos_market_id') : '',
            'market_name' => $channel === 'pos' ? (string) $order->get_meta('_bressol_pos_market_name') : '',
            'currency' => (string) $order->get_currency(),
            'total_incl_tax_cents' => $totalInclTax,
            'tax_total_cents' => $taxTotal,
            'total_excl_tax_cents' => $totalExclTax,
            'shipping_excl_tax_cents' => $shippingExcl,
            'shipping_tax_cents' => $shippingTax,
            'shipping_incl_tax_cents' => $shippingIncl,
            'discount_excl_tax_cents' => $discountExcl,
            'discount_tax_cents' => $discountTax,
            'discount_incl_tax_cents' => $discountIncl,
            'refunds_incl_tax_cents' => $refundsIncl,
            'market_cost_cents' => $marketCost,
            'net_sales_excl_tax_cents' => $netSalesExcl,
            'profit_estimated_excl_tax_cents' => $profitEstimated,
        ];
    }

    private function resolve_channel(\WC_Order $order): string
    {
        $meta = (string) $order->get_meta('_bressol_pos_channel');
        return $meta === 'pos' ? 'pos' : 'web';
    }

    private function to_cents(float $value): int
    {
        return (int) round($value * 100);
    }

    private function format_date(?\WC_DateTime $date): string
    {
        return $date ? $date->date('Y-m-d H:i:s') : '';
    }
}
