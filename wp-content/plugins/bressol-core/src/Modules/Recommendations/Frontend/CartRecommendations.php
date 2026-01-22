<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations\Frontend;

use Bressol\Modules\Recommendations\Domain\RecommendationRules;

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
        if (!function_exists('is_cart') || !is_cart()) return;
        if (!function_exists('WC') || !WC()->cart) return;

        $cartItems = WC()->cart->get_cart();
        if (empty($cartItems)) return;

        $inCart = [];
        $familiesInCart = [];

        foreach ($cartItems as $item) {
            $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if ($pid <= 0) continue;

            $inCart[$pid] = true;
            $familiesInCart[RecommendationRules::detectFamily($pid)] = true;
        }

        if (count($familiesInCart) > 1 && isset($familiesInCart['other'])) {
            unset($familiesInCart['other']);
        }

        $recommendations = RecommendationRules::buildRecommendations('cart', [
            'families' => array_keys($familiesInCart),
        ]);
        if (empty($recommendations)) return;

        // Deduplicar por product_id manteniendo el primer motivo
        $unique = [];
        foreach ($recommendations as $r) {
            $pid = (int) ($r['product_id'] ?? 0);
            if ($pid <= 0) continue;
            if (!isset($unique[$pid])) $unique[$pid] = $r;
        }
        $recommendations = array_values($unique);

        $valid = [];
        foreach ($recommendations as $rec) {
            $pid = (int) ($rec['product_id'] ?? 0);
            if ($pid <= 0) continue;

            // No recomendar algo que ya está en el carrito
            if (isset($inCart[$pid])) continue;

            $p = wc_get_product($pid);
            if (!$p) continue;

            $rec['product'] = $p;
            $valid[] = $rec;
        }

        if (empty($valid)) return;

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
                        'bressol_cart_reco_type' => (string) ($rec['type'] ?? 'cross_sell'),
                    ], get_permalink((int) $rec['product_id']));
                    ?>

                    <li style="margin:10px 0;">
                        <a class="bressol-cart-reco-link"
                           data-reco-type="<?php echo esc_attr((string) ($rec['type'] ?? 'cross_sell')); ?>"
                           data-reco-product-id="<?php echo esc_attr((string) $rec['product_id']); ?>"
                           href="<?php echo esc_url($targetUrl); ?>">
                            <?php echo esc_html($p->get_name()); ?>
                        </a>
                        — <strong><?php echo wp_kses_post(wc_price((float) $p->get_price())); ?></strong><br/>
                        <small style="color:#666;"><?php echo esc_html((string) ($rec['reason'] ?? '')); ?></small>
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
}