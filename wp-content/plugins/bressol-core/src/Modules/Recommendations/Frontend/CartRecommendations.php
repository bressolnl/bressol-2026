<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class CartRecommendations
{
    public function register(): void
    {
        add_action('woocommerce_after_cart_totals', [$this, 'render'], 12);
    }

    public function render(): void
    {
        if (!function_exists('is_cart') || !is_cart()) {
            return;
        }

        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        $cartItems = WC()->cart->get_cart();
        if (empty($cartItems)) {
            return;
        }

        $inCart = [];
        $familiesInCart = [];

        foreach ($cartItems as $item) {
            $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if ($pid <= 0) {
                continue;
            }

            $inCart[$pid] = true;
            $familiesInCart[$this->detectFamilyByProduct($pid)] = true;
        }

        // Limpiar 'other' si hay familias mejores
        if (count($familiesInCart) > 1 && isset($familiesInCart['other'])) {
            unset($familiesInCart['other']);
        }

        $recommendations = $this->buildCartRecommendations(array_keys($familiesInCart), $inCart);
        if (empty($recommendations)) {
            return;
        }

        // Deduplicar por product_id manteniendo el primer motivo
        $unique = [];
        foreach ($recommendations as $r) {
            $pid = (int) ($r['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            if (!isset($unique[$pid])) {
                $unique[$pid] = $r;
            }
        }
        $recommendations = array_values($unique);

        $valid = [];
        foreach ($recommendations as $rec) {
            $pid = (int) ($rec['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }

            // No recomendar algo que ya está en el carrito
            if (isset($inCart[$pid])) {
                continue;
            }

            $p = wc_get_product($pid);
            if (!$p) {
                continue;
            }

            $rec['product'] = $p;
            $valid[] = $rec;
        }

        if (empty($valid)) {
            return;
        }

        $recommendedIds = array_map(static fn($x) => (string) $x['product_id'], $valid);
        ?>
        <div class="bressol-cart-recommendations" style="margin-top:16px;padding:14px;border:1px solid #eee;">
            <h3 style="margin-top:0;">Recomendado para completar tu compra</h3>

            <ul style="margin:0 0 0 18px;">
                <?php foreach ($valid as $rec): ?>
                    <?php /** @var \WC_Product $p */ $p = $rec['product']; ?>

                    <?php
                    $targetUrl = add_query_arg([
                        'bressol_cart_reco_src'  => 'cart',
                        'bressol_cart_reco_type' => (string) $rec['type'],
                    ], get_permalink((int) $rec['product_id']));
                    ?>

                    <li style="margin:10px 0;">
                        <a class="bressol-cart-reco-link"
                           data-reco-type="<?php echo esc_attr($rec['type']); ?>"
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
              event: 'bressol_cart_reco_view',
              context: 'cart',
              families: <?php echo wp_json_encode(array_keys($familiesInCart)); ?>,
              recommended_product_ids: <?php echo wp_json_encode($recommendedIds); ?>
            });
          })();
        </script>
        <?php
    }

    private function buildCartRecommendations(array $families, array $inCart): array
    {
        $recs = [];

        // Si hay oil en carrito: pack oil + salt/vinegar
        if (in_array('oil', $families, true)) {
            $packId = $this->findPackByFocus('oil');
            if ($packId) {
                $recs[] = [
                    'product_id' => $packId,
                    'type'       => 'upsell_pack',
                    'reason'     => 'Ahorra y completa tu set con un pack de cocina.',
                ];
            }

            foreach ($this->findProductsByCategorySlug('salt', 2) as $pid) {
                $recs[] = [
                    'product_id' => $pid,
                    'type'       => 'cross_sell',
                    'reason'     => 'Complemento perfecto para tus aceites.',
                ];
            }

            foreach ($this->findProductsByCategorySlug('vinegar', 2) as $pid) {
                $recs[] = [
                    'product_id' => $pid,
                    'type'       => 'cross_sell',
                    'reason'     => 'Completa el set con un vinagre artesano.',
                ];
            }
        }

        // Si hay drinks: olives/tapenade + pack borrel
        if (in_array('drinks', $families, true)) {
            $packId = $this->findPackByTheme('borrel');
            if ($packId) {
                $recs[] = [
                    'product_id' => $packId,
                    'type'       => 'upsell_pack',
                    'reason'     => 'Mejor experiencia borrel: pack completo.',
                ];
            }

            foreach ($this->findProductsByCategorySlugs(['olives', 'tapenade'], 4) as $pid) {
                $recs[] = [
                    'product_id' => $pid,
                    'type'       => 'cross_sell',
                    'reason'     => 'Ideal para acompañar bebidas.',
                ];
            }
        }

        return $recs;
    }

    private function detectFamilyByProduct(int $productId): string
    {
        // 1) categorías
        $terms = get_the_terms($productId, 'product_cat');
        if (is_array($terms)) {
            $slugs = array_map(static fn($t) => strtolower((string) $t->slug), $terms);

            if (in_array('oil', $slugs, true)) return 'oil';
            if (in_array('drinks', $slugs, true)) return 'drinks';
            if (in_array('salt', $slugs, true) || in_array('vinegar', $slugs, true)) return 'oil';
            if (in_array('olives', $slugs, true) || in_array('tapenade', $slugs, true)) return 'drinks';
        }

        // 2) fallback por focus (packs)
        $focus = (string) get_post_meta($productId, '_bressol_pack_focus', true);
        if ($focus !== '') {
            $arr = array_filter(array_map('trim', explode(',', strtolower($focus))));
            if (in_array('oil', $arr, true)) return 'oil';
            if (in_array('drinks', $arr, true) || in_array('borrel', $arr, true)) return 'drinks';
        }

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