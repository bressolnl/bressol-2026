<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations\Domain;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fuente única de verdad para:
 * - Familias internas (oil, drinks, borrel_food, sweet, rice, packs, other)
 * - Slots (oil/salt/vinegar/drinks/olives/tapenade/preserves/snacks/other)
 * - Reglas de recomendación (PDP + Cart) y targets del modal (extras)
 */
final class RecommendationRules
{
    // --- Category slug groups ---
    private const PACK_SLUGS = ['packs', 'packs-only', 'gift-card', 'gifts', 'gift-box'];

    private const BORREL_FOOD_SLUGS = ['borrel', 'olives', 'tapenade', 'preserves', 'snacks'];

    // Drinks subcats (OJO: drinks incluye wine y dessert-liqueur, pero NO son borrel drinks)
    private const DRINKS_SLUGS = ['drinks', 'beer', 'aperitief', 'dessert-liqueur', 'wine'];

    // Lo que consideramos "bebida borrel" (lo que SÍ queremos recomendar con borrel_food)
    private const BORREL_DRINK_SLUGS = ['beer', 'aperitief'];

    private const OIL_BASE_SLUGS = ['smaakmakers', 'oil', 'salt', 'vinegar'];

    private const SWEET_SLUGS = ['sweet-breakfast', 'jam', 'infusions', 'orxata-fartons', 'honey', 'nougat', 'tarwerondjes'];

    private const RICE_SLUGS = ['rice-meals'];

    // --- Public API: detection ---
    public static function getCategorySlugs(int $productId): array
    {
        $terms = get_the_terms($productId, 'product_cat');
        if (!is_array($terms)) {
            return [];
        }
        $slugs = array_map(static fn($t) => strtolower((string) $t->slug), $terms);
        // unique + normalized
        $slugs = array_values(array_unique(array_filter($slugs)));
        return $slugs;
    }

    public static function detectFamily(int $productId): string
    {
        $slugs = self::getCategorySlugs($productId);

        if (self::hasAny($slugs, self::PACK_SLUGS)) {
            return 'packs';
        }

        if (self::hasAny($slugs, self::BORREL_FOOD_SLUGS)) {
            return 'borrel_food';
        }

        if (self::hasAny($slugs, self::DRINKS_SLUGS)) {
            return 'drinks';
        }

        if (self::hasAny($slugs, self::OIL_BASE_SLUGS)) {
            return 'oil';
        }

        if (self::hasAny($slugs, self::SWEET_SLUGS)) {
            return 'sweet';
        }

        if (self::hasAny($slugs, self::RICE_SLUGS)) {
            return 'rice';
        }

        // fallback por focus
        $focus = (string) get_post_meta($productId, '_bressol_pack_focus', true);
        if ($focus !== '') {
            $arr = array_filter(array_map('trim', explode(',', strtolower($focus))));
            if (in_array('oil', $arr, true)) return 'oil';
            if (in_array('drinks', $arr, true)) return 'drinks';
            if (in_array('borrel', $arr, true) || in_array('borrel_food', $arr, true)) return 'borrel_food';
            if (in_array('sweet', $arr, true)) return 'sweet';
            if (in_array('rice', $arr, true)) return 'rice';
            if (in_array('packs', $arr, true)) return 'packs';
        }

        return 'other';
    }

    /**
     * Slot = clave que usas para prefill/upgrade packs.
     * Importante: para borrel_food NO devolvemos "borrel_food" (eso rompe el prefill),
     * devolvemos el slug concreto (olives/tapenade/preserves/snacks) si aplica.
     */
    public static function detectSlot(int $productId): string
    {
        $slugs = self::getCategorySlugs($productId);

        if (in_array('oil', $slugs, true)) return 'oil';
        if (in_array('salt', $slugs, true)) return 'salt';
        if (in_array('vinegar', $slugs, true)) return 'vinegar';

        // Drinks: cualquier bebida -> slot drinks (en packs de drinks/cocktail)
        if (self::hasAny($slugs, self::DRINKS_SLUGS)) return 'drinks';

        // Borrel food: slot específico
        foreach (['olives', 'tapenade', 'preserves', 'snacks'] as $k) {
            if (in_array($k, $slugs, true)) return $k;
        }
        // si solo tiene el padre borrel sin subcat, slot borrel (por si algún pack usa "borrel")
        if (in_array('borrel', $slugs, true)) return 'borrel';

        return 'other';
    }

    public static function isBorrelDrinkProduct(int $productId): bool
    {
        $slugs = self::getCategorySlugs($productId);
        return self::hasAny($slugs, self::BORREL_DRINK_SLUGS);
    }

    // --- Public API: recommendation builders (central) ---

    /**
     * Recomendaciones PDP (lista de items con product_id/type/reason).
     * Nota: la obtención de IDs la hacemos aquí para que no se duplique la lógica.
     */
    public static function buildPdpRecommendations(int $sourceProductId): array
    {
        $family = self::detectFamily($sourceProductId);

        if ($family === 'oil') {
            $recs = [];

            $packId = self::findPackByFocus('oil');
            if ($packId && $packId !== $sourceProductId) {
                $recs[] = ['product_id' => $packId, 'type' => 'upsell_pack', 'reason' => 'Pack recomendado para cocina: combina aceite con complementos.'];
            }

            foreach (self::findProductsByCategorySlugs(['salt'], 2) as $pid) {
                if ($pid === $sourceProductId) continue;
                $recs[] = ['product_id' => $pid, 'type' => 'cross_sell', 'reason' => 'Complemento ideal para tu elección.'];
            }

            foreach (self::findProductsByCategorySlugs(['vinegar'], 2) as $pid) {
                if ($pid === $sourceProductId) continue;
                $recs[] = ['product_id' => $pid, 'type' => 'cross_sell', 'reason' => 'Completa tu set con un vinagre artesano.'];
            }

            return $recs;
        }

        if ($family === 'drinks') {
            $recs = [];

            // Pack borrel puede ser útil en bebidas (especialmente beer/aperitief)
            $packId = self::findPackByTheme('borrel');
            if ($packId && $packId !== $sourceProductId) {
                $recs[] = ['product_id' => $packId, 'type' => 'upsell_pack', 'reason' => 'Perfecto para acompañar bebidas: pack Borrel.'];
            }

            // Comida de borrel siempre ok con drinks
            foreach (self::findProductsByCategorySlugs(['olives', 'tapenade', 'preserves', 'snacks'], 4) as $pid) {
                if ($pid === $sourceProductId) continue;
                $recs[] = ['product_id' => $pid, 'type' => 'cross_sell', 'reason' => 'Ideal para borrel: añade algo para picar.'];
            }

            return $recs;
        }

        if ($family === 'borrel_food') {
            $recs = [];

            // 1) Pack borrel
            $packId = self::findPackByTheme('borrel');
            if ($packId && $packId !== $sourceProductId) {
                $recs[] = ['product_id' => $packId, 'type' => 'upsell_pack', 'reason' => 'Completa la mesa con un pack borrel.'];
            }

            // 2) Bebidas borrel SOLO beer/aperitief (NO wine, NO dessert-liqueur)
            foreach (self::findProductsByCategorySlugs(self::BORREL_DRINK_SLUGS, 4) as $pid) {
                if ($pid === $sourceProductId) continue;
                $recs[] = ['product_id' => $pid, 'type' => 'cross_sell', 'reason' => 'Una bebida borrel (beer/aperitief) para acompañar.'];
            }

            // 3) Más borrel food (tapenade/snacks/preserves/olives)
            foreach (self::findProductsByCategorySlugs(['olives', 'tapenade', 'preserves', 'snacks'], 4) as $pid) {
                if ($pid === $sourceProductId) continue;
                $recs[] = ['product_id' => $pid, 'type' => 'cross_sell', 'reason' => 'Completa la mesa con algo más de borrel.'];
            }

            return $recs;
        }

        return [];
    }

    /**
     * Recomendaciones Cart basadas en families presentes.
     * Devuelve lista de items (sin filtrar "ya está en el carrito"; eso lo haces fuera).
     */
    public static function buildCartRecommendations(array $families): array
    {
        $recs = [];

        if (in_array('oil', $families, true)) {
            $packId = self::findPackByFocus('oil');
            if ($packId) {
                $recs[] = ['product_id' => $packId, 'type' => 'upsell_pack', 'reason' => 'Ahorra y completa tu set con un pack de cocina.'];
            }

            foreach (self::findProductsByCategorySlugs(['salt'], 2) as $pid) {
                $recs[] = ['product_id' => $pid, 'type' => 'cross_sell', 'reason' => 'Complemento perfecto para tus aceites.'];
            }

            foreach (self::findProductsByCategorySlugs(['vinegar'], 2) as $pid) {
                $recs[] = ['product_id' => $pid, 'type' => 'cross_sell', 'reason' => 'Completa el set con un vinagre artesano.'];
            }
        }

        if (in_array('drinks', $families, true)) {
            $packId = self::findPackByTheme('borrel');
            if ($packId) {
                $recs[] = ['product_id' => $packId, 'type' => 'upsell_pack', 'reason' => 'Mejor experiencia borrel: pack completo.'];
            }

            foreach (self::findProductsByCategorySlugs(['olives', 'tapenade', 'preserves', 'snacks'], 4) as $pid) {
                $recs[] = ['product_id' => $pid, 'type' => 'cross_sell', 'reason' => 'Ideal para acompañar tus bebidas.'];
            }
        }

        if (in_array('borrel_food', $families, true)) {
            $packId = self::findPackByTheme('borrel');
            if ($packId) {
                $recs[] = ['product_id' => $packId, 'type' => 'upsell_pack', 'reason' => 'Completa la mesa con un pack borrel.'];
            }

            // SOLO beer + aperitief
            foreach (self::findProductsByCategorySlugs(self::BORREL_DRINK_SLUGS, 4) as $pid) {
                $recs[] = ['product_id' => $pid, 'type' => 'cross_sell', 'reason' => 'Bebida borrel recomendada (beer/aperitief).'];
            }
        }

        return $recs;
    }

    /**
     * Targets del modal (extras) por producto fuente.
     * IMPORTANTE: borrel_food -> SOLO beer/aperitief.
     */
    public static function extraTargetCategorySlugs(int $sourceProductId): array
    {
        $family = self::detectFamily($sourceProductId);

        if ($family === 'borrel_food') {
            return self::BORREL_DRINK_SLUGS; // NO drinks (porque drinks incluiría wine/dessert-liqueur)
        }

        if ($family === 'drinks') {
            return ['olives', 'tapenade', 'preserves', 'snacks'];
        }

        if ($family === 'oil') {
            $slugs = self::getCategorySlugs($sourceProductId);
            if (in_array('oil', $slugs, true)) return ['salt', 'vinegar'];
            if (in_array('salt', $slugs, true) || in_array('vinegar', $slugs, true)) return ['oil'];
            return ['salt', 'vinegar'];
        }

        return [];
    }

        /**
     * API estable única para consumidores (PDP / Cart / Modal extras).
     * - pdp: devuelve items con product_id/type/reason
     * - cart: devuelve items con product_id/type/reason
     * - modal_extras: devuelve items con product_id/title/price/currency/reason
     */
    public static function buildRecommendations(string $context, array $payload): array
    {
        if ($context === 'pdp') {
            return self::buildPdpRecommendations((int) ($payload['source_product_id'] ?? 0));
        }

        if ($context === 'cart') {
            $families = $payload['families'] ?? [];
            if (!is_array($families)) {
                $families = [];
            }
            return self::buildCartRecommendations($families);
        }

        if ($context === 'modal_extras') {
            $sourceProductId = (int) ($payload['source_product_id'] ?? 0);
            $limit = (int) ($payload['limit'] ?? 3);
            return self::buildModalExtras($sourceProductId, $limit);
        }

        return [];
    }

    /**
     * Builder para extras del modal (cross-sell rápido).
     * Usa extraTargetCategorySlugs() como origen de reglas para que:
     * - borrel_food => SOLO beer/aperitief
     * - drinks => borrel_food
     * - oil => salt/vinegar (o oil si viene de salt/vinegar)
     */
    public static function buildModalExtras(int $sourceProductId, int $limit): array
    {
        if ($sourceProductId <= 0 || $limit <= 0) {
            return [];
        }

        $targetSlugs = self::extraTargetCategorySlugs($sourceProductId);
        if (!$targetSlugs) {
            return [];
        }

        $ids = self::findProductsByCategorySlugs($targetSlugs, $limit);

        $out = [];
        foreach ($ids as $pid) {
            if ($pid === $sourceProductId) {
                continue;
            }

            $p = wc_get_product($pid);
            if (!$p) {
                continue;
            }

            $out[] = [
                'product_id' => (string) $pid,
                'title'      => $p->get_name(),
                'price'      => (float) $p->get_price(),
                'currency'   => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR',
                'reason'     => 'Ideal para acompañar tu elección.',
            ];
        }

        return $out;
    }


    // --- Internal helpers (query) ---
    private static function findPackByFocus(string $needle): ?int
    {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => '_bressol_pack_definition', 'compare' => 'EXISTS'],
                ['key' => '_bressol_pack_focus', 'compare' => 'EXISTS'],
            ],
        ];

        $ids = get_posts($args);
        if (!is_array($ids)) return null;

        $needle = strtolower($needle);

        foreach ($ids as $id) {
            $id = (int) $id;
            $focus = (string) get_post_meta($id, '_bressol_pack_focus', true);
            $arr = array_filter(array_map('trim', explode(',', strtolower($focus))));
            if (in_array($needle, $arr, true)) return $id;
        }

        return null;
    }

    private static function findPackByTheme(string $needle): ?int
    {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => '_bressol_pack_definition', 'compare' => 'EXISTS'],
                ['key' => '_bressol_pack_themes', 'compare' => 'EXISTS'],
            ],
        ];

        $ids = get_posts($args);
        if (!is_array($ids)) return null;

        $needle = strtolower($needle);

        foreach ($ids as $id) {
            $id = (int) $id;
            $themes = (string) get_post_meta($id, '_bressol_pack_themes', true);
            $arr = array_filter(array_map('trim', explode(',', strtolower($themes))));
            if (in_array($needle, $arr, true)) return $id;
        }

        return null;
    }

    public static function findProductsByCategorySlugs(array $slugs, int $limit): array
    {
        $slugs = array_values(array_filter(array_map('strval', $slugs)));
        if (!$slugs || $limit <= 0) return [];

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'tax_query'      => [
                ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slugs],
            ],
        ];

        $ids = get_posts($args);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    private static function hasAny(array $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (in_array($n, $haystack, true)) return true;
        }
        return false;
    }
}