<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class MarginAuditService
{
    private MarginRulesService $rulesService;

    public function __construct(?MarginRulesService $rulesService = null)
    {
        $this->rulesService = $rulesService ?? new MarginRulesService();
    }

    /** @param array<string, mixed> $rulesResult
     *  @param array<string, mixed> $context
     */
    public function persist_from_result(\WC_Order $order, array $rulesResult, array $context = []): void
    {
        $computed = isset($rulesResult['computed']) && is_array($rulesResult['computed'])
            ? $rulesResult['computed']
            : [];
        $netSales = (int) ($computed['net_sales_excl_tax_cents'] ?? 0);
        $marketCost = isset($context['market_cost_cents'])
            ? (int) $context['market_cost_cents']
            : (int) $order->get_meta('_bressol_pos_market_cost_cents');
        $cogs = (int) ($computed['cogs_cents'] ?? 0);
        $missingCost = !empty($computed['missing_cost']);
        $status = isset($rulesResult['status']) ? (string) $rulesResult['status'] : 'pass';
        $violations = isset($rulesResult['violations']) && is_array($rulesResult['violations'])
            ? array_values($rulesResult['violations'])
            : [];
        $priceSource = ($context['price_source'] ?? 'woo_fallback') === 'explicit' ? 'explicit' : 'woo_fallback';

        $cogsSource = $this->resolve_cogs_source($order);
        if ($cogsSource === 'real') {
            $real = $order->get_meta('_bressol_cogs_real_cents');
            if (is_numeric($real)) {
                $cogs = (int) $real;
                $missingCost = false;
            } else {
                $missingCost = true;
            }
        }

        if ($netSales <= 0 || $missingCost) {
            $status = 'warn';
        }

        $profit = $netSales - $marketCost - $cogs;
        $marginBps = $netSales > 0 ? (int) round(($profit / $netSales) * 10000) : 0;

        $computedPayload = [
            'net_sales_excl_tax_cents' => $netSales,
            'market_cost_cents' => $marketCost,
            'cogs_cents' => $cogs,
            'profit_cents' => $profit,
            'margin_bps' => $marginBps,
            'cogs_source' => $cogsSource,
            'missing_cost' => (bool) $missingCost,
            'price_source' => $priceSource,
        ];

        $violationsJson = wp_json_encode($violations);
        $computedJson = wp_json_encode($computedPayload);

        $order->update_meta_data('_bressol_margin_status', $status);
        $order->update_meta_data('_bressol_margin_violations', $violationsJson !== false ? $violationsJson : '[]');
        $order->update_meta_data('_bressol_margin_computed', $computedJson !== false ? $computedJson : '{}');
        $order->update_meta_data('_bressol_margin_rules_version', $this->get_rules_version());
        $order->update_meta_data('_bressol_margin_checked_at', current_time('mysql'));
        $order->save();
    }

    /** @param array<string, mixed> $context */
    public function evaluate_and_persist_order(\WC_Order $order, array $context = []): void
    {
        $channel = isset($context['channel']) ? (string) $context['channel'] : $this->resolve_channel($order);
        $marketCost = isset($context['market_cost_cents'])
            ? (int) $context['market_cost_cents']
            : ($channel === 'pos' ? (int) $order->get_meta('_bressol_pos_market_cost_cents') : 0);
        $items = $this->build_items_from_order($order);

        $result = $this->rulesService->evaluate_cart($items, [
            'channel' => $channel,
            'market_cost_cents' => $marketCost,
            'is_clearance' => !empty($context['is_clearance']),
        ]);

        $context['market_cost_cents'] = $marketCost;
        if (!isset($context['price_source'])) {
            $context['price_source'] = 'woo_fallback';
        }
        $this->persist_from_result($order, $result, $context);
    }

    private function resolve_cogs_source(\WC_Order $order): string
    {
        $status = (string) $order->get_meta('_bressol_cogs_status');
        if ($status === 'final') {
            return 'real';
        }
        if ($status !== '' && $status !== 'final') {
            return 'pending';
        }
        return 'estimated';
    }

    private function get_rules_version(): string
    {
        $normalized = $this->normalize_rules_for_version();
        $encoded = wp_json_encode($normalized);
        if (!is_string($encoded) || $encoded === '') {
            return 'v1';
        }

        return substr(sha1($encoded), 0, 12);
    }

    /** @return array<string, mixed> */
    private function normalize_rules_for_version(): array
    {
        $defaults = [
            'enable_rules' => true,
            'min_profit_cents_pos' => 300,
            'min_margin_pct_pos' => 0.10,
            'line_max_loss_cents_pos' => 500,
            'min_profit_cents_online' => 300,
            'min_margin_pct_online' => 0.10,
            'line_max_loss_cents_online' => 500,
            'expiry_discount_steps' => [
                45 => 25,
                21 => 50,
                7 => 75,
            ],
        ];

        $raw = get_option('bressol_margin_rules', []);
        $options = is_array($raw) ? array_merge($defaults, $raw) : $defaults;

        $normalized = [
            'enable_rules' => !empty($options['enable_rules']),
            'min_profit_cents_pos' => (int) ($options['min_profit_cents_pos'] ?? $defaults['min_profit_cents_pos']),
            'min_margin_pct_pos' => (float) ($options['min_margin_pct_pos'] ?? $defaults['min_margin_pct_pos']),
            'line_max_loss_cents_pos' => (int) ($options['line_max_loss_cents_pos'] ?? $defaults['line_max_loss_cents_pos']),
            'min_profit_cents_online' => (int) ($options['min_profit_cents_online'] ?? $defaults['min_profit_cents_online']),
            'min_margin_pct_online' => (float) ($options['min_margin_pct_online'] ?? $defaults['min_margin_pct_online']),
            'line_max_loss_cents_online' => (int) ($options['line_max_loss_cents_online'] ?? $defaults['line_max_loss_cents_online']),
            'expiry_discount_steps' => $this->normalize_expiry_steps(
                $options['expiry_discount_steps'] ?? null,
                $defaults['expiry_discount_steps']
            ),
        ];

        ksort($normalized);
        return $normalized;
    }

    /** @param mixed $rawSteps
     *  @param array<int,int> $fallback
     *  @return array<int,int>
     */
    private function normalize_expiry_steps($rawSteps, array $fallback): array
    {
        $steps = [];
        if (is_array($rawSteps)) {
            foreach ($rawSteps as $days => $discount) {
                $d = is_numeric($days) ? (int) $days : 0;
                $p = is_numeric($discount) ? (int) $discount : -1;
                if ($d <= 0 || $p < 0 || $p > 100) {
                    continue;
                }
                $steps[$d] = $p;
            }
        }

        if ($steps === []) {
            $steps = $fallback;
        }

        krsort($steps);
        return $steps;
    }

    /** @return array<int, array<string, mixed>> */
    private function build_items_from_order(\WC_Order $order): array
    {
        $items = [];
        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $productId = (int) $item->get_product_id();
            $qty = (int) $item->get_quantity();
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $lineTotal = (float) $item->get_total();
            $unitPrice = $qty > 0 ? $lineTotal / $qty : 0.0;
            $priceExclCents = (int) round($unitPrice * 100);

            $packSelection = $this->extract_pack_selection($item);

            $items[] = [
                'product_id' => $productId,
                'qty' => $qty,
                'price_excl_tax_cents' => $priceExclCents,
                'pack_selection' => $packSelection,
            ];
        }

        return $items;
    }

    /** @return array<int,int>|null */
    private function extract_pack_selection(\WC_Order_Item_Product $item): ?array
    {
        $raw = $item->get_meta('_bressol_pack', true);
        $pack = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($pack)) {
            return null;
        }
        $selections = $pack['selections'] ?? null;
        if (!is_array($selections)) {
            return null;
        }

        $selection = [];
        foreach ($selections as $lines) {
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $pid = isset($line['product_id']) ? (int) $line['product_id'] : 0;
                $qty = isset($line['qty']) ? (int) $line['qty'] : 0;
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $selection[$pid] = ($selection[$pid] ?? 0) + $qty;
            }
        }

        return $selection === [] ? null : $selection;
    }

    private function resolve_channel(\WC_Order $order): string
    {
        $meta = (string) $order->get_meta('_bressol_pos_channel');
        return $meta === 'pos' ? 'pos' : 'online';
    }
}
