<?php
declare(strict_types=1);

namespace Bressol\Modules\GuidedShopping\Wizard;

if (!defined('ABSPATH')) {
    exit;
}

final class UpsellRecommender
{
    /**
     * Devuelve una lista de upsells recomendados.
     * MVP: mezcla packs upgrade + placeholder caja regalo.
     *
     * @return array<int, array{type:string,title:string,description:string,cta:string,url:?string}>
     */
    public function recommend(?string $budget, ?string $occasion, array $currentPackIds = []): array
    {
        $upsells = [];

        // 1) Placeholder: caja regalo si es para regalo
        if ($occasion === 'gift') {
            $upsells[] = [
                'type' => 'placeholder',
                'title' => 'Añade caja regalo',
                'description' => 'Presentación premium para regalo (añadiremos el producto real más adelante).',
                'cta' => 'Quiero caja regalo',
                'url' => null,
            ];
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
                        'title' => 'Mejora recomendada',
                        'description' => 'Opción superior que encaja con tu selección.',
                        'cta' => 'Ver pack premium',
                        'url' => get_permalink($candidate),
                    ];
                }
            }
        }

        return $upsells;
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

        // Filtro por occasion si existe meta
        $occ = (string) get_post_meta($id, '_bressol_pack_occasion', true);
        if ($occasion === 'gift') {
            if ($occ !== '' && $occ !== 'gift' && $occ !== 'both') return null;
        } elseif ($occasion === 'self') {
            if ($occ !== '' && $occ !== 'self' && $occ !== 'both') return null;
        }

        return $id;
    }
}