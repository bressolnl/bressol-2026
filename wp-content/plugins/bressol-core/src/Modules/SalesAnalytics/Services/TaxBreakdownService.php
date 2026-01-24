<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class TaxBreakdownService
{
    private const VALIDATION_TOLERANCE_CENTS = 2;

    /** @return array<string, array{tax_cents:int, taxable_base_cents:int}> */
    public function breakdown_for_order(\WC_Order $order): array
    {
        $breakdown = [];

        $this->add_items_breakdown($breakdown, $order->get_items('line_item'));
        $this->add_items_breakdown($breakdown, $order->get_items('shipping'));

        foreach ($order->get_refunds() as $refund) {
            if (!$refund instanceof \WC_Order_Refund) {
                continue;
            }

            $this->add_items_breakdown($breakdown, $refund->get_items('line_item'));
            $this->add_items_breakdown($breakdown, $refund->get_items('shipping'));
        }

        return $breakdown;
    }

    /** @param array<string, array{tax_cents:int, taxable_base_cents:int}> $breakdown
     *  @return array{ok:bool, diff_tax_cents:int, message:string}
     */
    public function validate_order(\WC_Order $order, array $breakdown): array
    {
        $sumTaxCents = 0;
        foreach ($breakdown as $row) {
            $sumTaxCents += (int) $row['tax_cents'];
        }

        $orderTaxCents = $this->to_cents((float) $order->get_total_tax());
        $refundedTaxCents = $this->get_refunded_tax_cents($order);
        $expectedTaxCents = $orderTaxCents - $refundedTaxCents;

        $diff = $sumTaxCents - $expectedTaxCents;
        $ok = abs($diff) <= self::VALIDATION_TOLERANCE_CENTS;

        return [
            'ok' => $ok,
            'diff_tax_cents' => $diff,
            'message' => $ok ? 'OK' : 'Diferencia en impuestos',
        ];
    }

    /** @param iterable<\WC_Order> $orders
     *  @return array<string, array{tax_cents:int, taxable_base_cents:int, orders_count:int}>
     */
    public function aggregate_breakdown(iterable $orders): array
    {
        $aggregate = [];

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $breakdown = $this->breakdown_for_order($order);
            $touchedRates = [];

            foreach ($breakdown as $rate => $row) {
                if (!isset($aggregate[$rate])) {
                    $aggregate[$rate] = [
                        'tax_cents' => 0,
                        'taxable_base_cents' => 0,
                        'orders_count' => 0,
                    ];
                }

                $aggregate[$rate]['tax_cents'] += (int) $row['tax_cents'];
                $aggregate[$rate]['taxable_base_cents'] += (int) $row['taxable_base_cents'];
                $touchedRates[$rate] = true;
            }

            foreach (array_keys($touchedRates) as $rate) {
                $aggregate[$rate]['orders_count']++;
            }
        }

        return $aggregate;
    }

    /** @param array<string, array{tax_cents:int, taxable_base_cents:int}> $breakdown
     *  @param array<int, \WC_Order_Item> $items
     */
    private function add_items_breakdown(array &$breakdown, array $items): void
    {
        foreach ($items as $item) {
            if (!$item instanceof \WC_Order_Item) {
                continue;
            }

            $taxes = $item->get_taxes();
            $taxMap = isset($taxes['total']) && is_array($taxes['total']) ? $taxes['total'] : [];
            if ($taxMap === []) {
                continue;
            }

            $totalExclTaxCents = $this->to_cents((float) $item->get_total());
            $taxCentsByRate = [];
            $totalTaxCents = 0;

            foreach ($taxMap as $rateId => $taxAmount) {
                $taxCents = $this->to_cents((float) $taxAmount);
                $taxCentsByRate[(string) $rateId] = $taxCents;
                $totalTaxCents += $taxCents;
            }

            $baseAllocations = $this->allocate_base_by_tax($totalExclTaxCents, $taxCentsByRate);

            foreach ($taxCentsByRate as $rateId => $taxCents) {
                $rateKey = $this->normalize_rate_percent($rateId);
                $baseCents = $baseAllocations[$rateId] ?? 0;
                $this->add_rate_row($breakdown, $rateKey, $taxCents, $baseCents);
            }
        }
    }

    /** @param array<string, int> $taxCentsByRate
     *  @return array<string, int>
     */
    private function allocate_base_by_tax(int $totalExclTaxCents, array $taxCentsByRate): array
    {
        $allocations = [];
        $rateIds = array_keys($taxCentsByRate);
        $count = count($rateIds);

        if ($count === 0) {
            return $allocations;
        }

        $totalTaxCents = array_sum($taxCentsByRate);
        if ($totalTaxCents === 0) {
            $allocations[$rateIds[0]] = $totalExclTaxCents;
            return $allocations;
        }

        $allocated = 0;
        foreach ($rateIds as $index => $rateId) {
            $taxCents = $taxCentsByRate[$rateId] ?? 0;
            if ($index === $count - 1) {
                $allocations[$rateId] = $totalExclTaxCents - $allocated;
                break;
            }

            $share = (float) $taxCents / (float) $totalTaxCents;
            $baseCents = (int) round($totalExclTaxCents * $share);
            $allocations[$rateId] = $baseCents;
            $allocated += $baseCents;
        }

        return $allocations;
    }

    /** @param array<string, array{tax_cents:int, taxable_base_cents:int}> $breakdown */
    private function add_rate_row(array &$breakdown, string $rateKey, int $taxCents, int $baseCents): void
    {
        if (!isset($breakdown[$rateKey])) {
            $breakdown[$rateKey] = [
                'tax_cents' => 0,
                'taxable_base_cents' => 0,
            ];
        }

        $breakdown[$rateKey]['tax_cents'] += $taxCents;
        $breakdown[$rateKey]['taxable_base_cents'] += $baseCents;
    }

    private function normalize_rate_percent(string $rateId): string
    {
        if (!class_exists('\\WC_Tax')) {
            return '0.00';
        }

        $raw = \WC_Tax::get_rate_percent((int) $rateId);
        $normalized = str_replace('%', '', (string) $raw);
        $value = (float) $normalized;

        return number_format($value, 2, '.', '');
    }

    private function to_cents(float $value): int
    {
        return (int) round($value * 100);
    }

    private function get_refunded_tax_cents(\WC_Order $order): int
    {
        $refundedTaxCents = 0;

        foreach ($order->get_refunds() as $refund) {
            if (!$refund instanceof \WC_Order_Refund) {
                continue;
            }

            foreach ($refund->get_items(['line_item', 'shipping']) as $item) {
                if (!$item instanceof \WC_Order_Item) {
                    continue;
                }

                $taxes = $item->get_taxes();
                $taxMap = isset($taxes['total']) && is_array($taxes['total']) ? $taxes['total'] : [];
                foreach ($taxMap as $taxAmount) {
                    $refundedTaxCents += abs($this->to_cents((float) $taxAmount));
                }
            }
        }

        return $refundedTaxCents;
    }
}
