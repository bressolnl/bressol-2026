<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductRepository
{
    public function get_product(int $productId): ?\WC_Product
    {
        if ($productId <= 0 || !function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($productId);
        return $product instanceof \WC_Product ? $product : null;
    }

    public function manages_stock(\WC_Product $product): bool
    {
        return (bool) $product->get_manage_stock();
    }

    public function get_stock_quantity(\WC_Product $product): ?int
    {
        $qty = $product->get_stock_quantity();
        return $qty === null ? null : (int) $qty;
    }

    public function get_product_name(int $productId): string
    {
        $product = $this->get_product($productId);
        return $product ? (string) $product->get_name() : '';
    }

    /** @return array<int, int> */
    public function find_pack_ids(string $query, int $limit): array
    {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => PackDefinitionRepository::META_KEY, 'compare' => 'EXISTS'],
            ],
        ];

        if ($query !== '') {
            if (ctype_digit($query)) {
                $args['p'] = (int) $query;
            } else {
                $args['s'] = $query;
            }
        }

        $ids = get_posts($args);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }
}
