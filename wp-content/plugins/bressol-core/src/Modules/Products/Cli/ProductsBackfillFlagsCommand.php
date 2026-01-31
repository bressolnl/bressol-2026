<?php
declare(strict_types=1);

namespace Bressol\Modules\Products\Cli;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductsBackfillFlagsCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        if (!class_exists('\\WooCommerce')) {
            \WP_CLI::error('WooCommerce no disponible.');
        }

        $dryRun = $this->to_bool($assocArgs['dry-run'] ?? '0');
        $page = 1;
        $perPage = 200;
        $rows = [];
        $processed = 0;
        $updated = 0;
        $skipped = 0;

        do {
            $ids = wc_get_products([
                'status' => ['publish', 'draft', 'pending', 'private'],
                'limit' => $perPage,
                'page' => $page,
                'return' => 'ids',
            ]);
            $page++;

            foreach ($ids as $productId) {
                $productId = (int) $productId;
                if ($productId <= 0) {
                    continue;
                }
                $processed++;

                $product = wc_get_product($productId);
                if (!$product instanceof \WC_Product) {
                    continue;
                }

                $categories = $this->get_category_slugs($productId);
                $isBeverage = $this->has_category_marker($categories, $this->beverage_markers());
                $isPackaging = $this->has_category_marker($categories, $this->packaging_markers());

                $newIsAlcohol = $isBeverage ? 1 : 0;
                $newTaxClass = ($isBeverage || $isPackaging) ? '' : 'reduced-rate';
                $reason = $isPackaging ? 'packaging/accesorios' : ($isBeverage ? 'bebidas' : 'default reducido');

                $currentTaxClass = (string) $product->get_tax_class();
                $currentIsAlcohol = (string) get_post_meta($productId, '_bressol_is_alcohol', true);
                $needsAlcohol = $currentIsAlcohol !== (string) $newIsAlcohol;
                $needsTaxClass = $currentTaxClass !== $newTaxClass;

                if (!$needsAlcohol && !$needsTaxClass) {
                    $skipped++;
                    continue;
                }

                if (!$dryRun) {
                    if ($needsAlcohol) {
                        update_post_meta($productId, '_bressol_is_alcohol', $newIsAlcohol);
                        $this->apply_alcohol_attribute($productId, $newIsAlcohol);
                    }
                    if ($needsTaxClass) {
                        update_post_meta($productId, '_tax_class', $newTaxClass);
                        $product->set_tax_class($newTaxClass);
                        $product->save();
                    }
                }

                $updated++;
                $rows[] = [
                    'product_id' => $productId,
                    'sku' => $product->get_sku(),
                    'title' => $product->get_name(),
                    'current_tax_class' => $currentTaxClass,
                    'new_tax_class' => $newTaxClass,
                    'reason' => $reason,
                ];
            }
        } while (!empty($ids));

        \WP_CLI::log('dry_run=' . ($dryRun ? '1' : '0') . ' processed=' . $processed . ' updated=' . $updated . ' skipped=' . $skipped);
        if (!empty($rows)) {
            \WP_CLI\Utils\format_items('table', $rows, ['product_id', 'sku', 'title', 'current_tax_class', 'new_tax_class', 'reason']);
        } else {
            \WP_CLI::success('No hay cambios pendientes.');
        }
    }

    private function get_category_slugs(int $productId): array
    {
        $terms = get_the_terms($productId, 'product_cat');
        if (!is_array($terms)) {
            return [];
        }
        $slugs = [];
        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $slugs[] = (string) $term->slug;
            }
        }
        return $slugs;
    }

    private function has_category_marker(array $slugs, array $markers): bool
    {
        foreach ($slugs as $slug) {
            foreach ($markers as $marker) {
                if (strpos($slug, $marker) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    private function beverage_markers(): array
    {
        return ['bebida', 'bebidas', 'beverage', 'beverages'];
    }

    private function packaging_markers(): array
    {
        return ['packaging', 'accessory', 'accessories', 'accesorio', 'accesorios'];
    }

    private function apply_alcohol_attribute(int $postId, int $isAlcohol): void
    {
        $taxonomy = 'pa_alcohol';
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        $terms = $this->resolve_alcohol_terms($taxonomy);
        if ($terms === []) {
            return;
        }

        $termSlug = $isAlcohol === 1 ? $terms['yes'] : $terms['no'];
        if ($termSlug === '') {
            return;
        }

        wp_set_object_terms($postId, [$termSlug], $taxonomy, false);
        $this->ensure_product_attribute_meta($postId, $taxonomy);
    }

    private function resolve_alcohol_terms(string $taxonomy): array
    {
        $hasJa = term_exists('ja', $taxonomy);
        $hasNee = term_exists('nee', $taxonomy);
        if ($hasJa && $hasNee) {
            return ['yes' => 'ja', 'no' => 'nee'];
        }

        $hasYes = term_exists('yes', $taxonomy);
        $hasNo = term_exists('no', $taxonomy);
        if ($hasYes && $hasNo) {
            return ['yes' => 'yes', 'no' => 'no'];
        }

        $createdYes = term_exists('yes', $taxonomy) ? 'yes' : '';
        $createdNo = term_exists('no', $taxonomy) ? 'no' : '';
        if ($createdYes === '') {
            $created = wp_insert_term('Yes', $taxonomy, ['slug' => 'yes']);
            if (!is_wp_error($created)) {
                $createdYes = 'yes';
            }
        }
        if ($createdNo === '') {
            $created = wp_insert_term('No', $taxonomy, ['slug' => 'no']);
            if (!is_wp_error($created)) {
                $createdNo = 'no';
            }
        }

        if ($createdYes !== '' && $createdNo !== '') {
            return ['yes' => $createdYes, 'no' => $createdNo];
        }
        return [];
    }

    private function ensure_product_attribute_meta(int $postId, string $taxonomy): void
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($postId);
        if (!$product instanceof \WC_Product) {
            return;
        }

        $attributes = $product->get_attributes();
        if (isset($attributes[$taxonomy])) {
            return;
        }

        $attribute = new \WC_Product_Attribute();
        $attribute->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
        $attribute->set_name($taxonomy);
        $attribute->set_visible(true);
        $attribute->set_variation(false);
        $attributes[$taxonomy] = $attribute;
        $product->set_attributes($attributes);
        $product->save();
    }

    private function to_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'yes', 'true', 'on'], true);
    }
}
