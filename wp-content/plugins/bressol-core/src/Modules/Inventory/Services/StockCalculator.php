<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Services;

use Bressol\Modules\Inventory\Repositories\ProductRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class StockCalculator
{
    private ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
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

        $qty = $this->productRepository->get_stock_quantity($product);
        return $qty === null ? 0 : max(0, $qty);
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

            $product = $this->productRepository->get_product((int) $productId);
            if (!$product) {
                return [
                    'sellable' => 0,
                    'bottleneck_product_id' => (int) $productId,
                ];
            }

            if (!$this->productRepository->manages_stock($product)) {
                continue;
            }

            $stockQty = $this->productRepository->get_stock_quantity($product);
            if ($stockQty === null) {
                return [
                    'sellable' => 0,
                    'bottleneck_product_id' => (int) $productId,
                ];
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
