<?php
declare(strict_types=1);

namespace Bressol\Modules\GuidedShopping\Wizard;

if (!defined('ABSPATH')) {
    exit;
}

final class UpsellRecommender
{
    // IDs reales (WooCommerce products)
    private const GIFT_BOX_PRODUCT_ID = 80;
    private const GIFT_CARD_PRODUCT_ID = 81;

    /**
     * @return array<int, array{type:string,product_id:?int,title:string,description:string,cta:string,url:?string}>
     */
    public function recommend(?string $budget, ?string $occasion, array $currentPackIds = []): array
    {
        $upsells = [];

        // 1) Upsells de regalo (reales) si occasion = gift
        if ($occasion === 'gift') {
            // Caja regalo
            $upsells[] = $this->productUpsell(
                self::GIFT_BOX_PRODUCT_ID,
                'Añade caja regalo',
                'Presentación premium para regalo.',
                'Añadir caja regalo'
            );

            // Tarjeta
            $upsells[] = $this->productUpsell(
                self::GIFT_CARD_PRODUCT_ID,
                'Añade tarjeta dedicatoria',
                'Incluye un mensaje personalizado.',
                'Añadir tarjeta'
            );
        }

        // 2) Upgrade de pack por tier (si existen packs etiquetados)
        $targetTier = null;
        if ($budget === 'low')  $targetTier = 'mid';
        if ($budget === 'mid')  $targetTier = 'high';

        if ($targetTier) {
            $candidate = $this->findFirstPackByTier($targetTier, $occasion);
            if ($candidate && !in_array($candidate, $currentPackIds, true)) {
                $p = wc_get_product($candidate);
                if ($p) {
                    $upsells[] = [
                        'type' => 'pack_upgrade',
                        'product_id' => $candidate,
                        'title' => 'Mejora recomendada',
                        'description' => 'Opción superior que encaja con tu selección.',
                        'cta' => 'Ver pack premium',
                        'url' => get_permalink($candidate),
                    ];
                }
            }
        }

        // filtra nulos (por si algún producto no existe)
        return array_values(array_filter($upsells));
    }

    private function productUpsell(int $productId, string $title, string $description, string $cta): ?array
    {
        if (!function_exists('wc_get_product')) {
            return null;
        }
        $p = wc_get_product($productId);
        if (!$p) {
            return null;
        }

        return [
            'type' => 'product',
            'product_id' => $productId,
            'title' => $title,
            'description' => $description,
            'cta' => $cta,
            'url' => null,
        ];
    }

    private function findFirstPackByTier(string $tier, ?string $occasion): ?int
    {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_bressol_pack_definition',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => '_bressol_pack_tier',
                    'value'   => $tier,
                    'compare' => '=',
                ],
            ],
        ];

        $ids = get_posts($args);
        if (!is_array($ids) || !$ids) {
            return null;
        }

        $id = (int) $ids[0];

        $occ = (string) get_post_meta($id, '_bressol_pack_occasion', true);
        if ($occasion === 'gift') {
            if ($occ !== '' && $occ !== 'gift' && $occ !== 'both') return null;
        } elseif ($occasion === 'self') {
            if ($occ !== '' && $occ !== 'self' && $occ !== 'both') return null;
        }

        return $id;
    }
}