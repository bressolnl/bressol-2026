<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductPageRecommendations
{
    public function register(): void
    {
        add_action('woocommerce_after_single_product_summary', [$this, 'render'], 12);
    }

    public function render(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $sourceProductId = (int) $product->get_id();
        $family = $this->detectFamilyByCategory($sourceProductId);
        $slotKey = $this->detectSlotByCategory($sourceProductId);

        $items = $this->getRecommendationsForFamily($family, $sourceProductId);
        if (empty($items)) {
            return;
        }

        $valid = [];
        foreach ($items as $item) {
            $p = wc_get_product($item['product_id']);
            if (!$p) {
                continue;
            }
            $item['product'] = $p;
            $valid[] = $item;
        }

        if (empty($valid)) {
            return;
        }

        $recommendedIds = array_map(fn($x) => (string) $x['product_id'], $valid);
        ?>
        <div class="bressol-recommendations" style="margin-top:16px;padding:14px;border:1px solid #eee;">
            <h3 style="margin-top:0;">Recomendado para ti</h3>

            <ul style="margin:0 0 0 18px;">
                <?php foreach ($valid as $rec): ?>
                    <?php /** @var \WC_Product $p */ $p = $rec['product']; ?>

                    <?php
                    // Default: link normal (reco)
                    $targetArgs = [
                        'bressol_reco_src'  => (string) $sourceProductId,
                        'bressol_reco_type' => (string) $rec['type'],
                    ];

                    // Si es pack, lo tratamos como UPGRADE (prefill)
                    if (($rec['type'] ?? '') === 'upsell_pack' && $slotKey !== 'other') {
                        $targetArgs['bressol_reco_type'] = 'upgrade_pack';

                        $targetArgs['bressol_upgrade'] = '1';
                        $targetArgs['bressol_upgrade_src'] = 'pdp';
                        $targetArgs['bressol_upgrade_source_product_id'] = (string) $sourceProductId;

                        $targetArgs['bressol_prefill_slot'] = $slotKey;                 // oil|salt|vinegar|drinks
                        $targetArgs['bressol_prefill_product_id'] = (string) $sourceProductId;
                    }

                    $targetUrl = add_query_arg($targetArgs, get_permalink((int) $rec['product_id']));
                    ?>

                    <li style="margin:10px 0;">
                        <a class="bressol-reco-link"
                           data-reco-type="<?php echo esc_attr((string) $targetArgs['bressol_reco_type']); ?>"
                           data-reco-product-id="<?php echo esc_attr((string) $rec['product_id']); ?>"
                           href="<?php echo esc_url($targetUrl); ?>">
                            <?php echo esc_html($p->get_name()); ?>
                        </a>
                        — <strong><?php echo wp_kses_post(wc_price((float) $p->get_price())); ?></strong><br/>
                        <small style="color:#666;"><?php echo esc_html($rec['reason']); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <script>
          (function(){
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
              event: 'bressol_reco_view',
              context: 'pdp',
              source_product_id: '<?php echo esc_js((string)$sourceProductId); ?>',
              family: '<?php echo esc_js($family); ?>',
              recommended_product_ids: <?php echo wp_json_encode($recommendedIds); ?>
            });
          })();
        </script>
        <?php
    }

    private function getRecommendationsForFamily(string $family, int $sourceProductId): array
    {
        if ($family === 'oil') {
            $recs = [];

            $packId = $this->findPackByFocus('oil');
            if ($packId && $packId !== $sourceProductId) {
                $recs[] = [
                    'product_id' => $packId,
                    'type'       => 'upsell_pack',
                    'reason'     => 'Pack recomendado para cocina: combina aceite con complementos.',
                ];
            }

            foreach ($this->findProductsByCategorySlug('salt', 2) as $pid) {
                $recs[] = [
                    'product_id' => $pid,
                    'type'       => 'cross_sell',
                    'reason'     => 'Complemento ideal para el aceite.',
                ];
            }

            foreach ($this->findProductsByCategorySlug('vinegar', 2) as $pid) {
                $recs[] = [
                    'product_id' => $pid,
                    'type'       => 'cross_sell',
                    'reason'     => 'Completa tu set con vinagre artesano.',
                ];
            }

            return $recs;
        }

        if ($family === 'drinks') {
            $recs = [];

            $packId = $this->findPackByTheme('borrel');
            if ($packId) {
                $recs[] = [
                    'product_id' => $packId,
                    'type'       => 'upsell_pack',
                    'reason'     => 'Perfecto para acompañar bebidas: pack Borrel.',
                ];
            }

            foreach ($this->findProductsByCategorySlugs(['olives', 'tapenade'], 4) as $pid) {
                $recs[] = [
                    'product_id' => $pid,
                    'type'       => 'cross_sell',
                    'reason'     => 'Ideal para aperitivo (borrel).',
                ];
            }

            return $recs;
        }

        return [];
    }

    private function detectFamilyByCategory(int $productId): string
    {
        $terms = get_the_terms($productId, 'product_cat');
        if (!is_array($terms)) {
            return 'other';
        }

        $slugs = array_map(fn($t) => strtolower((string) $t->slug), $terms);

        if (in_array('oil', $slugs, true)) return 'oil';
        if (in_array('drinks', $slugs, true)) return 'drinks';
        if (in_array('tapenade', $slugs, true) || in_array('olives', $slugs, true)) return 'borrel';

        // Nota: salt/vinegar los tratamos como "familia oil" para reglas,
        // pero el slot se detecta aparte (detectSlotByCategory).
        if (in_array('salt', $slugs, true) || in_array('vinegar', $slugs, true)) return 'oil';

        // Fallback por focus (packs/productos sin categorías)
        $focus = (string) get_post_meta($productId, '_bressol_pack_focus', true);
        if ($focus !== '') {
            $arr = array_filter(array_map('trim', explode(',', strtolower($focus))));
            if (in_array('oil', $arr, true)) return 'oil';
            if (in_array('drinks', $arr, true) || in_array('borrel', $arr, true)) return 'drinks';
            if (in_array('sweet', $arr, true)) return 'sweet';
        }

        return 'other';
    }

    // NUEVO: detecta qué slot hay que prefijar (oil/salt/vinegar/drinks)
    private function detectSlotByCategory(int $productId): string
    {
        $terms = get_the_terms($productId, 'product_cat');
        if (!is_array($terms)) {
            return 'other';
        }

        $slugs = array_map(fn($t) => strtolower((string) $t->slug), $terms);

        if (in_array('oil', $slugs, true)) return 'oil';
        if (in_array('salt', $slugs, true)) return 'salt';
        if (in_array('vinegar', $slugs, true)) return 'vinegar';
        if (in_array('drinks', $slugs, true)) return 'drinks';

        return 'other';
    }

    private function findPackByFocus(string $needle): ?int
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

        foreach ($ids as $id) {
            $id = (int) $id;
            $focus = (string) get_post_meta($id, '_bressol_pack_focus', true);
            $arr = array_filter(array_map('trim', explode(',', strtolower($focus))));
            if (in_array(strtolower($needle), $arr, true)) {
                return $id;
            }
        }

        return null;
    }

    private function findPackByTheme(string $needle): ?int
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

        foreach ($ids as $id) {
            $id = (int) $id;
            $themes = (string) get_post_meta($id, '_bressol_pack_themes', true);
            $arr = array_filter(array_map('trim', explode(',', strtolower($themes))));
            if (in_array(strtolower($needle), $arr, true)) {
                return $id;
            }
        }

        return null;
    }

    private function findProductsByCategorySlug(string $slug, int $limit): array
    {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'tax_query'      => [
                ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [$slug]],
            ],
        ];

        $ids = get_posts($args);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    private function findProductsByCategorySlugs(array $slugs, int $limit): array
    {
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
}