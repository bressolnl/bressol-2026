<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Services;

use Bressol\Modules\Inventory\Lots\Services\LotService;
use Bressol\Modules\Inventory\Repositories\ProductRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class StockCalculator
{
    private ProductRepository $productRepository;
    private LotService $lotService;

    public function __construct(ProductRepository $productRepository, ?LotService $lotService = null)
    {
        $this->productRepository = $productRepository;
        $this->lotService = $lotService ?? new LotService();
    }

    public function get_simple_sellable_qty(int $productId): int
    {
        $product = $this->productRepository->get_product($productId);
        if (!$product) {
            return 0;
        }

        if (!$this->productRepository->manages_stock($product)) {
            return PHP_INT_MAX;
        }

        $qty = $this->lotService->get_available_qty($productId, 'NL', true, current_time('Y-m-d'));
        return max(0, $qty);
    }

    /** @param array<int, int> $requirements product_id => qty */
    public function get_pack_sellable_from_requirements(array $requirements): array
    {
        if ($requirements === []) {
            return [
                'sellable' => 0,
                'bottleneck_product_id' => 0,
            ];
        }

        $sellable = PHP_INT_MAX;
        $bottleneckProductId = 0;

        foreach ($requirements as $productId => $qtyPerPack) {
            if ($qtyPerPack <= 0) {
                continue;
            }

            $stockQty = $this->get_simple_sellable_qty((int) $productId);
            if ($stockQty === PHP_INT_MAX) {
                continue;
            }

            $possible = (int) floor($stockQty / $qtyPerPack);
            if ($possible < $sellable) {
                $sellable = $possible;
                $bottleneckProductId = (int) $productId;
            }
        }

        return [
            'sellable' => max(0, $sellable),
            'bottleneck_product_id' => $bottleneckProductId,
        ];
    }
}
