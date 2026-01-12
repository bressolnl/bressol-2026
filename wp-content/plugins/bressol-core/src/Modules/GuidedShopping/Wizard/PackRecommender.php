<?php
declare(strict_types=1);

namespace Bressol\Modules\GuidedShopping\Wizard;

if (!defined('ABSPATH')) {
    exit;
}

final class PackRecommender
{
    private const META_KEY = '_bressol_pack_definition';

    /**
     * @return int[]
     */
    public function recommend(?string $budget, ?string $occasion = null): array
    {
        if (!$budget) {
            return [];
        }

        if (!function_exists('wc_get_product')) {
            return [];
        }

        // Fallback por precio (temporal si no hay tier)
        $lowMax = 35.0;
        $midMax = 75.0;

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::META_KEY,
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        $ids = get_posts($args);
        if (!is_array($ids) || !$ids) {
            return [];
        }

        $filtered = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }

            $tier = (string) get_post_meta($id, '_bressol_pack_tier', true);      // low|mid|high|''
            $occ  = (string) get_post_meta($id, '_bressol_pack_occasion', true); // gift|self|both|''

            // 1) Filtrar por ocasión (si el wizard la tiene)
            if ($occasion === 'gift') {
                if ($occ !== '' && $occ !== 'gift' && $occ !== 'both') {
                    continue;
                }
            } elseif ($occasion === 'self') {
                if ($occ !== '' && $occ !== 'self' && $occ !== 'both') {
                    continue;
                }
            }

            // 2) Filtrar por budget usando tier si existe; si no, fallback por precio
            $matchesBudget = false;

            if ($tier !== '') {
                if ($budget === 'low' && $tier === 'low')  $matchesBudget = true;
                if ($budget === 'mid' && $tier === 'mid')  $matchesBudget = true;
                if ($budget === 'high' && $tier === 'high') $matchesBudget = true;
            } else {
                $price = (float) $product->get_price();
                if ($budget === 'low' && $price <= $lowMax) $matchesBudget = true;
                if ($budget === 'mid' && $price > $lowMax && $price <= $midMax) $matchesBudget = true;
                if ($budget === 'high' && $price > $midMax) $matchesBudget = true;
            }

            if (!$matchesBudget) {
                continue;
            }

            $filtered[] = $id;
        }

        // Orden simple por precio ascendente
        usort($filtered, function (int $a, int $b): int {
            $pa = (float) (wc_get_product($a)?->get_price() ?? 0);
            $pb = (float) (wc_get_product($b)?->get_price() ?? 0);
            return $pa <=> $pb;
        });

        return $filtered;
    }
}