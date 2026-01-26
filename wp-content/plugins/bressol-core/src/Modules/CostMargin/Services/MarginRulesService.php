<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Services;

use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class MarginRulesService
{
    private CostMarginService $costService;
    private LotRepository $lotRepository;

    public function __construct(?CostMarginService $costService = null, ?LotRepository $lotRepository = null)
    {
        $this->costService = $costService ?? new CostMarginService();
        $this->lotRepository = $lotRepository ?? new LotRepository();
    }

    /** @return array<int, array{name:string,items:array<int, array<string, mixed>>,context:array<string, mixed>,expect:string}> */
    public function get_selftest_cases(string $channel): array
    {
        $channel = $channel === 'online' ? 'online' : 'pos';

        return [
            [
                'name' => 'pass_basic',
                'items' => [
                    ['product_id' => 1, 'qty' => 1, 'price_excl_tax_cents' => 2000],
                ],
                'context' => [
                    'channel' => $channel,
                    'market_cost_cents' => 0,
                    'unit_cost_overrides' => [1 => 1000],
                ],
                'expect' => 'pass',
            ],
            [
                'name' => 'block_profit',
                'items' => [
                    ['product_id' => 2, 'qty' => 1, 'price_excl_tax_cents' => 1200],
                ],
                'context' => [
                    'channel' => $channel,
                    'market_cost_cents' => 0,
                    'unit_cost_overrides' => [2 => 1000],
                ],
                'expect' => 'block',
            ],
            [
                'name' => 'block_margin',
                'items' => [
                    ['product_id' => 3, 'qty' => 1, 'price_excl_tax_cents' => 5000],
                ],
                'context' => [
                    'channel' => $channel,
                    'market_cost_cents' => 0,
                    'unit_cost_overrides' => [3 => 4700],
                ],
                'expect' => 'block',
            ],
            [
                'name' => 'block_net_sales_zero',
                'items' => [
                    ['product_id' => 4, 'qty' => 1, 'price_excl_tax_cents' => 0],
                ],
                'context' => [
                    'channel' => $channel,
                    'market_cost_cents' => 0,
                    'unit_cost_overrides' => [4 => 100],
                ],
                'expect' => 'block',
            ],
            [
                'name' => 'warn_line_loss',
                'items' => [
                    ['product_id' => 5, 'qty' => 1, 'price_excl_tax_cents' => 400],
                ],
                'context' => [
                    'channel' => $channel,
                    'market_cost_cents' => 0,
                    'unit_cost_overrides' => [5 => 600],
                ],
                'expect' => 'block',
            ],
            [
                'name' => 'warn_line_loss_clearance',
                'items' => [
                    ['product_id' => 7, 'qty' => 1, 'price_excl_tax_cents' => 400],
                ],
                'context' => [
                    'channel' => $channel,
                    'market_cost_cents' => 0,
                    'is_clearance' => true,
                    'unit_cost_overrides' => [7 => 600],
                ],
                'expect' => 'warn',
            ],
            [
                'name' => 'warn_missing_cost',
                'items' => [
                    ['product_id' => 6, 'qty' => 1, 'price_excl_tax_cents' => 5000],
                ],
                'context' => [
                    'channel' => $channel,
                    'market_cost_cents' => 0,
                    'unit_cost_overrides' => [6 => null],
                ],
                'expect' => 'warn',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $items
     *  @param array<string, mixed> $context
     *  @return array{status:string,violations:array<int, array<string, mixed>>,computed:array<string, mixed>}
     */
    public function evaluate_cart(array $items, array $context): array
    {
        $violations = [];
        $computed = [
            'net_sales_excl_tax_cents' => 0,
            'cogs_cents' => 0,
            'profit_cents' => 0,
            'margin_pct' => 0,
            'missing_cost' => false,
        ];

        $settings = $this->get_settings((string) ($context['channel'] ?? 'pos'));
        if (!$settings['enable_rules']) {
            return [
                'status' => 'pass',
                'violations' => [],
                'computed' => $computed,
            ];
        }
        $expirySteps = $settings['expiry_discount_steps'];
        $unitCostOverrides = [];
        if (isset($context['unit_cost_overrides']) && is_array($context['unit_cost_overrides'])) {
            $unitCostOverrides = $context['unit_cost_overrides'];
        }

        $isClearance = !empty($context['is_clearance']);
        $marketCost = (int) ($context['market_cost_cents'] ?? 0);
        $netSales = 0;
        $cogs = 0;
        $missingCost = false;
        $priceEstimated = false;
        $expiryCheckIds = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
            if ($productId <= 0 || $qty <= 0) {
                $this->add_violation($violations, 'invalid_item', 'warn', 'Item inválido.', [
                    'product_id' => $productId,
                ]);
                continue;
            }

            $estimatedPrice = false;
            $priceExcl = $this->resolve_price_excl_tax_cents($item, $estimatedPrice);
            if ($priceExcl <= 0) {
                $this->add_violation($violations, 'missing_price', 'warn', 'Precio no disponible.', [
                    'product_id' => $productId,
                ]);
            }
            if ($estimatedPrice) {
                $priceEstimated = true;
                $this->add_violation($violations, 'pos_price_missing', 'warn', 'Precio estimado desde catálogo.', [
                    'product_id' => $productId,
                ]);
            }

            $lineRevenue = max(0, $priceExcl) * $qty;
            $netSales += $lineRevenue;

            $packSelection = $item['pack_selection'] ?? null;
            if (is_array($packSelection)) {
                $lineCost = 0;
                $selectionValid = false;
                foreach ($packSelection as $compId => $compQty) {
                    $cid = is_numeric($compId) ? (int) $compId : 0;
                    $cq = is_numeric($compQty) ? (int) $compQty : 0;
                    if ($cid <= 0 || $cq < 1 || $cq > 500) {
                        $this->add_violation($violations, 'invalid_pack_entry', 'warn', 'Selección pack inválida.', [
                            'product_id' => $productId,
                        ]);
                        continue;
                    }
                    $selectionValid = true;
                    $expiryCheckIds[$cid] = true;
                    $unitCost = $this->resolve_unit_cost_override($unitCostOverrides, $cid);
                    if ($unitCost === null) {
                        $unitCost = $this->costService->get_unit_cost_cents($cid, 'NL', current_time('Y-m-d'));
                    }
                    if ($unitCost === null) {
                        $missingCost = true;
                        $this->add_violation($violations, 'missing_unit_cost', 'warn', 'Coste unitario no disponible.', [
                            'product_id' => $cid,
                        ]);
                        continue;
                    }
                    $lineCost += $unitCost * ($cq * $qty);
                }

                if (!$selectionValid) {
                    $missingCost = true;
                }

                $cogs += $lineCost;
                $lineProfit = $lineRevenue - $lineCost;
                $this->apply_line_rules($violations, $productId, $lineProfit, $settings, $isClearance);
            } else {
                $expiryCheckIds[$productId] = true;
                $unitCost = $this->resolve_unit_cost_override($unitCostOverrides, $productId);
                if ($unitCost === null) {
                    $unitCost = $this->costService->get_unit_cost_cents($productId, 'NL', current_time('Y-m-d'));
                }
                if ($unitCost === null) {
                    $missingCost = true;
                    $this->add_violation($violations, 'missing_unit_cost', 'warn', 'Coste unitario no disponible.', [
                        'product_id' => $productId,
                    ]);
                    $unitCost = 0;
                }
                $lineCost = $unitCost * $qty;
                $cogs += $lineCost;
                $lineProfit = $lineRevenue - $lineCost;
                $this->apply_line_rules($violations, $productId, $lineProfit, $settings, $isClearance);
            }
        }

        $profit = $netSales - $marketCost - $cogs;
        $marginPct = $netSales > 0 ? $profit / $netSales : 0.0;

        $computed = [
            'net_sales_excl_tax_cents' => $netSales,
            'cogs_cents' => $cogs,
            'profit_cents' => $profit,
            'margin_pct' => $marginPct,
            'missing_cost' => $missingCost,
        ];

        if ($netSales <= 0) {
            $this->add_violation($violations, 'ticket_net_sales_non_positive', 'block', 'Total neto inválido.', [
                'net_sales_excl_tax_cents' => $netSales,
            ]);
        } else {
            if ($profit < 0) {
                $severity = ($isClearance || $missingCost) ? 'warn' : 'block';
                $this->add_violation($violations, 'ticket_profit_negative', $severity, 'Ticket con pérdida.', [
                    'profit_cents' => $profit,
                ]);
            }
            if ($profit < $settings['min_profit_cents']) {
                $severity = ($isClearance || $missingCost || $priceEstimated) ? 'warn' : 'block';
                $this->add_violation($violations, 'ticket_profit_below_min', $severity, 'Profit mínimo no alcanzado.', [
                    'profit_cents' => $profit,
                    'min_profit_cents' => $settings['min_profit_cents'],
                ]);
            }
            if ($marginPct < $settings['min_margin_pct']) {
                $severity = ($isClearance || $missingCost || $priceEstimated) ? 'warn' : 'block';
                $this->add_violation($violations, 'ticket_margin_below_min', $severity, 'Margen mínimo no alcanzado.', [
                    'margin_pct' => $marginPct,
                    'min_margin_pct' => $settings['min_margin_pct'],
                ]);
            }
        }

        if ($missingCost) {
            $this->add_violation($violations, 'missing_cost', 'warn', 'Costes incompletos.', []);
        }

        $this->append_expiry_warnings($violations, array_keys($expiryCheckIds), $expirySteps);

        $status = 'pass';
        foreach ($violations as $violation) {
            if (($violation['severity'] ?? '') === 'block') {
                $status = 'block';
                break;
            }
            if (($violation['severity'] ?? '') === 'warn') {
                $status = 'warn';
            }
        }

        return [
            'status' => $status,
            'violations' => $violations,
            'computed' => $computed,
        ];
    }

    /** @return array{enable_rules:bool,min_profit_cents:int,min_margin_pct:float,line_max_loss_cents:int,expiry_discount_steps:array<int,int>} */
    private function get_settings(string $channel): array
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
        $expirySteps = $this->normalize_expiry_steps($options['expiry_discount_steps'] ?? null, $defaults['expiry_discount_steps']);

        $channel = $channel === 'online' ? 'online' : 'pos';

        return [
            'enable_rules' => !empty($options['enable_rules']),
            'min_profit_cents' => (int) ($options['min_profit_cents_' . $channel] ?? $defaults['min_profit_cents_pos']),
            'min_margin_pct' => (float) ($options['min_margin_pct_' . $channel] ?? $defaults['min_margin_pct_pos']),
            'line_max_loss_cents' => (int) ($options['line_max_loss_cents_' . $channel] ?? $defaults['line_max_loss_cents_pos']),
            'expiry_discount_steps' => $expirySteps,
        ];
    }

    /** @param array<int, array<string, mixed>> $violations */
    private function add_violation(array &$violations, string $code, string $severity, string $message, array $data): void
    {
        $violations[] = [
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'data' => $data,
        ];
    }

    private function resolve_price_excl_tax_cents(array $item, bool &$estimated): int
    {
        $estimated = false;
        if (isset($item['price_excl_tax_cents']) && is_numeric($item['price_excl_tax_cents'])) {
            return (int) $item['price_excl_tax_cents'];
        }

        $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        if ($productId <= 0 || !function_exists('wc_get_product')) {
            return 0;
        }

        $product = wc_get_product($productId);
        if (!$product instanceof \WC_Product) {
            return 0;
        }

        if (function_exists('wc_get_price_excluding_tax')) {
            $price = wc_get_price_excluding_tax($product, ['qty' => 1]);
        } else {
            $price = (float) $product->get_price();
        }

        $estimated = true;
        return (int) round($price * 100);
    }

    /** @param array<int, array<string, mixed>> $violations */
    private function apply_line_rules(array &$violations, int $productId, int $lineProfit, array $settings, bool $isClearance): void
    {
        if ($lineProfit >= 0) {
            return;
        }

        if ($lineProfit < 0) {
            $severity = $isClearance ? 'warn' : 'warn';
            $this->add_violation($violations, 'line_loss_negative', $severity, 'Línea con pérdida.', [
                'product_id' => $productId,
                'loss_cents' => $lineProfit,
            ]);
        }

        if ($lineProfit < (0 - $settings['line_max_loss_cents'])) {
            $severity = $isClearance ? 'warn' : 'block';
            $this->add_violation($violations, 'line_loss_exceeds_max', $severity, 'Pérdida excesiva en línea.', [
                'product_id' => $productId,
                'loss_cents' => $lineProfit,
                'max_loss_cents' => $settings['line_max_loss_cents'],
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $violations
     *  @param array<int, int> $productIds
     */
    private function append_expiry_warnings(array &$violations, array $productIds, array $steps): void
    {
        if ($productIds === []) {
            return;
        }

        $today = current_time('Y-m-d');
        $todayDate = \DateTimeImmutable::createFromFormat('Y-m-d', $today, wp_timezone());
        if (!$todayDate) {
            return;
        }

        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }

            $lot = $this->find_fifo_lot($productId, $today);
            if (!$lot) {
                continue;
            }

            $expiry = (string) ($lot['expiry_date'] ?? '');
            if ($expiry === '') {
                continue;
            }

            $expiryDate = \DateTimeImmutable::createFromFormat('Y-m-d', $expiry, wp_timezone());
            if (!$expiryDate) {
                continue;
            }

            $days = (int) $todayDate->diff($expiryDate)->format('%r%a');
            if ($days > 45) {
                continue;
            }

            $suggested = $this->resolve_expiry_discount($days, $steps);

            $this->add_violation($violations, 'expiry_risk', 'warn', 'Producto cercano a caducidad.', [
                'product_id' => $productId,
                'days_remaining' => $days,
                'suggested_discount_pct' => $suggested,
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    private function find_fifo_lot(int $productId, string $today): ?array
    {
        $lots = $this->lotRepository->get_lots_for_product_location($productId, 'NL');
        foreach ($lots as $lot) {
            $qty = (int) ($lot['qty_on_hand'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $expiry = (string) ($lot['expiry_date'] ?? '');
            if ($expiry !== '' && $expiry < $today) {
                continue;
            }
            return $lot;
        }

        return null;
    }

    private function resolve_expiry_discount(int $days, array $steps): int
    {
        foreach ($steps as $threshold => $discount) {
            if ($days <= (int) $threshold) {
                return (int) $discount;
            }
        }

        return 25;
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

    /** @param array<mixed, mixed> $overrides */
    private function resolve_unit_cost_override(array $overrides, int $productId): ?int
    {
        if ($productId <= 0) {
            return null;
        }

        foreach ($overrides as $key => $value) {
            $pid = is_numeric($key) ? (int) $key : 0;
            if ($pid !== $productId) {
                continue;
            }
            if ($value === null) {
                return null;
            }
            if (is_numeric($value)) {
                $cost = (int) $value;
                return $cost > 0 ? $cost : null;
            }
            return null;
        }

        return null;
    }
}
