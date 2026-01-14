<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations\Frontend;

use Bressol\Modules\Recommendations\Domain\RecommendationRules;

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
        if (!function_exists('is_product') || !is_product()) return;

        global $product;
        if (!$product) return;

        $sourceProductId = (int) $product->get_id();

        $family  = RecommendationRules::detectFamily($sourceProductId);
        $slotKey = RecommendationRules::detectSlot($sourceProductId);

        $items = RecommendationRules::buildPdpRecommendations($sourceProductId);
        if (empty($items)) return;

        $valid = [];
        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            if ($pid <= 0) continue;

            // no auto-recomendarse a sí mismo
            if ($pid === $sourceProductId) continue;

            $p = wc_get_product($pid);
            if (!$p) continue;

            $item['product'] = $p;
            $valid[] = $item;
        }
        if (empty($valid)) return;

        $recommendedIds = array_map(static fn($x) => (string) $x['product_id'], $valid);
        ?>
        <div class="bressol-recommendations" style="margin-top:16px;padding:14px;border:1px solid #eee;">
            <h3 style="margin-top:0;">Recomendado para ti</h3>

            <ul style="margin:0 0 0 18px;">
                <?php foreach ($valid as $rec): ?>
                    <?php /** @var \WC_Product $p */ $p = $rec['product']; ?>

                    <?php
                    $targetArgs = [
                        'bressol_reco_src'  => (string) $sourceProductId,
                        'bressol_reco_type' => (string) ($rec['type'] ?? 'cross_sell'),
                    ];

                    // Si es pack y hay slot útil, lo tratamos como UPGRADE (prefill)
                    if (($rec['type'] ?? '') === 'upsell_pack' && $slotKey !== 'other') {
                        $targetArgs['bressol_reco_type'] = 'upgrade_pack';
                        $targetArgs['bressol_upgrade'] = '1';
                        $targetArgs['bressol_upgrade_src'] = 'pdp';
                        $targetArgs['bressol_upgrade_source_product_id'] = (string) $sourceProductId;

                        $targetArgs['bressol_prefill_slot'] = $slotKey; // oil|salt|vinegar|drinks|olives|tapenade|preserves|snacks|borrel
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
                        <small style="color:#666;"><?php echo esc_html((string) ($rec['reason'] ?? '')); ?></small>
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
}