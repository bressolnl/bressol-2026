<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Services;

use Bressol\Modules\Inventory\Repositories\PackDefinitionRepository;
use Bressol\Modules\Inventory\Repositories\ProductRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class SellableService
{
    private const PACK_CACHE_TTL = 300;

    private ProductRepository $productRepository;
    private PackDefinitionRepository $packRepository;
    private StockCalculator $stockCalculator;
    private CacheService $cacheService;
    private AuditLogger $auditLogger;

    public function __construct(
        ProductRepository $productRepository,
        PackDefinitionRepository $packRepository,
        StockCalculator $stockCalculator,
        CacheService $cacheService,
        AuditLogger $auditLogger
    ) {
        $this->productRepository = $productRepository;
        $this->packRepository = $packRepository;
        $this->stockCalculator = $stockCalculator;
        $this->cacheService = $cacheService;
        $this->auditLogger = $auditLogger;
    }

    public static function build_default(CacheService $cacheService, AuditLogger $auditLogger): self
    {
        $productRepository = new ProductRepository();
        $packRepository = new PackDefinitionRepository();
        $stockCalculator = new StockCalculator($productRepository);

        return new self($productRepository, $packRepository, $stockCalculator, $cacheService, $auditLogger);
    }

    public function is_pack_product(int $productId): bool
    {
        return $this->packRepository->is_pack_product($productId);
    }

    public function is_sellable(int $productId, int $qty, ?array $selection = null): bool
    {
        if ($qty <= 0) {
            return true;
        }

        if ($this->is_pack_product($productId)) {
            $result = $this->get_pack_sellable($productId, $selection);
            return $result['sellable'] >= $qty;
        }

        $sellable = $this->stockCalculator->get_simple_sellable_qty($productId);
        return $sellable >= $qty;
    }

    /** @return array{sellable:int,bottleneck_product_id:int} */
    public function get_pack_sellable(int $packProductId, ?array $selection = null): array
    {
        if (is_array($selection) && $selection !== []) {
            $requirements = $selection;
            $result = $this->stockCalculator->get_pack_sellable_from_requirements($requirements);
            return $result;
        }

        $cached = $this->cacheService->get_pack($packProductId);
        if (is_array($cached) && isset($cached['sellable'])) {
            return [
                'sellable' => (int) $cached['sellable'],
                'bottleneck_product_id' => (int) ($cached['bottleneck_product_id'] ?? 0),
            ];
        }

        $result = $this->calculate_pack_sellable_from_definition($packProductId);
        $this->cacheService->set_pack($packProductId, $result, self::PACK_CACHE_TTL);

        return [
            'sellable' => (int) $result['sellable'],
            'bottleneck_product_id' => (int) ($result['bottleneck_product_id'] ?? 0),
        ];
    }

    public function extract_pack_selection_from_request(int $packProductId, array $request): array
    {
        $input = $request['bressol_pack'] ?? null;
        if (!is_array($input) || $input === []) {
            $fallback = $request['bressol_pack_config'] ?? null;
            if (is_array($fallback) && $fallback !== []) {
                $input = $fallback;
            }
        }

        if (!is_array($input) || $input === []) {
            return [];
        }

        return $this->packRepository->normalize_selection_from_request($packProductId, $input);
    }

    public function extract_pack_selection_from_cart_item(array $cartItem): array
    {
        return $this->packRepository->normalize_selection_from_cart_item($cartItem);
    }

    public function build_unavailable_message(int $productId, ?array $selection): string
    {
        $productName = $this->productRepository->get_product_name($productId);
        $label = $productName !== '' ? $productName : ('Producto #' . $productId);

        if ($this->is_pack_product($productId)) {
            $result = $this->get_pack_sellable($productId, $selection);
            $bottleneckId = (int) ($result['bottleneck_product_id'] ?? 0);
            $bottleneckName = $bottleneckId > 0 ? $this->productRepository->get_product_name($bottleneckId) : '';
            if ($bottleneckName !== '') {
                return 'Stock insuficiente para "' . $label . '". Componente limitante: ' . $bottleneckName . '.';
            }
            return 'Stock insuficiente para "' . $label . '".';
        }

        return 'Stock insuficiente para "' . $label . '".';
    }

    /** @param array<int, int>|null $selection */
    public function log_blocked(string $source, int $productId, int $qty, ?array $selection = null): void
    {
        $payload = [
            'source' => $source,
            'product_id' => $productId,
            'qty' => $qty,
        ];
        if ($this->is_pack_product($productId)) {
            $payload['is_pack'] = true;
            $payload['selection_items'] = is_array($selection) ? count($selection) : 0;
        }

        $this->auditLogger->log('inventory_blocked', $payload);
    }

    /** @return array<int, array<string, mixed>> */
    public function list_packs(string $query, int $limit): array
    {
        $ids = $this->productRepository->find_pack_ids($query, $limit);
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($ids as $packId) {
            $product = $this->productRepository->get_product($packId);
            if (!$product) {
                continue;
            }

            $result = $this->get_pack_sellable($packId);
            $bottleneckId = (int) ($result['bottleneck_product_id'] ?? 0);

            $out[] = [
                'product_id' => $packId,
                'product_name' => (string) $product->get_name(),
                'sellable' => (int) $result['sellable'],
                'bottleneck_product_id' => $bottleneckId,
                'bottleneck_product_name' => $bottleneckId > 0 ? $this->productRepository->get_product_name($bottleneckId) : '',
            ];
        }

        return $out;
    }

    /** @return array{sellable:int,bottleneck_product_id:int} */
    private function calculate_pack_sellable_from_definition(int $packProductId): array
    {
        $definition = $this->packRepository->get_definition($packProductId);
        if (!$definition || empty($definition['slots']) || !is_array($definition['slots'])) {
            return [
                'sellable' => 0,
                'bottleneck_product_id' => 0,
            ];
        }

        $perSlotSellable = [];
        $perSlotBottleneck = [];

        foreach ($definition['slots'] as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $required = !empty($slot['required']);
            $min = (int) ($slot['min'] ?? 0);
            $qtyPerPack = $min > 0 ? $min : ($required ? 1 : 0);
            if ($qtyPerPack <= 0) {
                continue;
            }

            $options = $slot['options'] ?? [];
            if (!is_array($options) || $options === []) {
                continue;
            }

            $best = 0;
            $bestBottleneck = 0;

            foreach ($options as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $optionProductId = (int) ($option['product_id'] ?? 0);
                if ($optionProductId <= 0) {
                    continue;
                }

                $requirements = [$optionProductId => $qtyPerPack];
                $result = $this->stockCalculator->get_pack_sellable_from_requirements($requirements);
                if ($result['sellable'] > $best) {
                    $best = $result['sellable'];
                    $bestBottleneck = $result['bottleneck_product_id'] ?: $optionProductId;
                }
            }

            $perSlotSellable[] = $best;
            $perSlotBottleneck[] = $bestBottleneck;
        }

        if ($perSlotSellable === []) {
            return [
                'sellable' => 0,
                'bottleneck_product_id' => 0,
            ];
        }

        $sellable = min($perSlotSellable);
        $slotIndex = array_search($sellable, $perSlotSellable, true);
        $bottleneck = $slotIndex !== false ? (int) ($perSlotBottleneck[$slotIndex] ?? 0) : 0;

        return [
            'sellable' => (int) $sellable,
            'bottleneck_product_id' => $bottleneck,
        ];
    }
}
