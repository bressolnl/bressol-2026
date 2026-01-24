<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class MetricsExtractor
{
    private ?EventRepository $eventRepository = null;

    /** @return array<string, int|string> */
    public function extract(\WC_Order $order): array
    {
        if ($this->is_internal_order($order)) {
            return $this->build_internal_payload($order);
        }

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
        $eventPayload = $this->resolve_event_payload($order);
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
            'event_id' => $eventPayload['event_id'],
            'event_title' => $eventPayload['event_title'],
            'event_cost_cents' => $eventPayload['event_cost_cents'],
            'market_cost_cents' => $marketCost,
            'net_sales_excl_tax_cents' => $netSalesExcl,
            'profit_estimated_excl_tax_cents' => $profitEstimated,
            'is_internal' => 0,
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

    private function is_internal_order(\WC_Order $order): bool
    {
        $meta = (string) $order->get_meta('_bressol_internal_order');
        if ($meta === '1') {
            return true;
        }

        return (string) $order->get_status() === 'bressol-internal';
    }

    /** @return array<string, int|string> */
    private function build_internal_payload(\WC_Order $order): array
    {
        $channel = $this->resolve_channel($order);
        $createdAt = $this->format_date($order->get_date_created());

        return [
            'order_id' => (int) $order->get_id(),
            'order_number' => (string) $order->get_order_number(),
            'created_at' => $createdAt,
            'status' => (string) $order->get_status(),
            'channel' => $channel,
            'market_id' => '',
            'market_name' => '',
            'currency' => (string) $order->get_currency(),
            'total_incl_tax_cents' => 0,
            'tax_total_cents' => 0,
            'total_excl_tax_cents' => 0,
            'shipping_excl_tax_cents' => 0,
            'shipping_tax_cents' => 0,
            'shipping_incl_tax_cents' => 0,
            'discount_excl_tax_cents' => 0,
            'discount_tax_cents' => 0,
            'discount_incl_tax_cents' => 0,
            'refunds_incl_tax_cents' => 0,
            'event_id' => 0,
            'event_title' => '',
            'event_cost_cents' => 0,
            'market_cost_cents' => 0,
            'net_sales_excl_tax_cents' => 0,
            'profit_estimated_excl_tax_cents' => 0,
            'is_internal' => 1,
        ];
    }

    private function get_event_repository(): EventRepository
    {
        if ($this->eventRepository === null) {
            $this->eventRepository = new EventRepository();
        }

        return $this->eventRepository;
    }

    /** @return array{event_id:int,event_title:string,event_cost_cents:int} */
    private function resolve_event_payload(\WC_Order $order): array
    {
        $eventIdRaw = $order->get_meta('_bressol_event_id');
        $eventId = is_numeric($eventIdRaw) ? (int) $eventIdRaw : 0;
        if ($eventId <= 0) {
            return [
                'event_id' => 0,
                'event_title' => '',
                'event_cost_cents' => 0,
            ];
        }

        $event = $this->get_event_repository()->find_by_id($eventId);
        if (!$event) {
            return [
                'event_id' => $eventId,
                'event_title' => '',
                'event_cost_cents' => 0,
            ];
        }

        $title = (string) ($event['title'] ?? '');
        $boothFee = (int) ($event['booth_fee_cents'] ?? 0);
        $otherCosts = (int) ($event['other_costs_cents'] ?? 0);

        return [
            'event_id' => $eventId,
            'event_title' => $title,
            'event_cost_cents' => $boothFee + $otherCosts,
        ];
    }
}
