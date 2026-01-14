<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations\Domain;

if (!defined('ABSPATH')) exit;

final class FamilyResolver
{
    /**
     * Devuelve familia interna estable:
     * drinks|borrel_food|oil|sweet|rice|packs|other
     */
    public function resolve(int $productId): string
    {
        if ($productId <= 0) return 'other';

        // 1) Por categorías Woo
        $slugs = $this->getProductCategorySlugs($productId);

        if ($slugs) {
            // Packs / gifts
            if ($this->hasAny($slugs, ['packs', 'packs-only', 'gift-card', 'gifts', 'gift-box'])) {
                return 'packs';
            }

            // Borrel food (incluye padre borrel)
            if ($this->hasAny($slugs, ['borrel', 'olives', 'tapenade', 'preserves', 'snacks'])) {
                return 'borrel_food';
            }

            // Drinks
            if ($this->hasAny($slugs, ['drinks', 'beer', 'aperitief', 'dessert-liqueur', 'wine'])) {
                return 'drinks';
            }

            // Kitchen base
            if ($this->hasAny($slugs, ['smaakmakers', 'oil', 'vinegar', 'salt'])) {
                return 'oil';
            }

            // Rice
            if ($this->hasAny($slugs, ['rice-meals'])) {
                return 'rice';
            }

            // Sweet (soporta el typo actual)
            if ($this->hasAny($slugs, [
                'sweet-breackfast', 'sweet-breakfast',
                'jam', 'infusions', 'orxata-fartons', 'honey', 'nougat', 'tarwerondjes'
            ])) {
                return 'sweet';
            }
        }

        // 2) Fallback por focus de pack (si algún producto/pack aún no tiene categorías)
        $focus = (string) get_post_meta($productId, '_bressol_pack_focus', true);
        if ($focus !== '') {
            $arr = array_filter(array_map('trim', explode(',', strtolower($focus))));
            if (in_array('oil', $arr, true)) return 'oil';
            if (in_array('drinks', $arr, true)) return 'drinks';
            if (in_array('borrel', $arr, true)) return 'borrel_food';
            if (in_array('sweet', $arr, true)) return 'sweet';
            if (in_array('rice', $arr, true)) return 'rice';
            if (in_array('packs', $arr, true)) return 'packs';
        }

        return 'other';
    }

    private function getProductCategorySlugs(int $productId): array
    {
        $terms = get_the_terms($productId, 'product_cat');
        if (!is_array($terms)) return [];
        $slugs = array_map(static fn($t) => strtolower((string) $t->slug), $terms);
        $slugs = array_values(array_unique(array_filter($slugs)));
        return $slugs;
    }

    private function hasAny(array $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (in_array($n, $haystack, true)) return true;
        }
        return false;
    }
}