<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductPageRecommendations
{
    // Config MVP por IDs (tu catálogo mínimo)
    private const PACK_COCINA_ID = 88;
    private const VINEGAR_ID = 83;
    private const SALT_ID = 84;
    private const OLIVES_ID = 85;
    private const TAPENADE_ID = 86;

    public function register(): void
    {
        add_action('woocommerce_after_single_product_summary', [$this, 'render'], 12);
    
        add_action('wp_ajax_bressol_reco_click', [$this, 'ajaxRecoClick']);
        add_action('wp_ajax_nopriv_bressol_reco_click', [$this, 'ajaxRecoClick']);
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

        $items = $this->getRecommendationsForFamily($family);
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
                    <li style="margin:10px 0;">
                        <a class="bressol-reco-link"
                           data-reco-type="<?php echo esc_attr($rec['type']); ?>"
                           data-reco-product-id="<?php echo esc_attr((string)$rec['product_id']); ?>"
                           <?php
$targetUrl = add_query_arg([
    'bressol_reco_src'  => (string) $sourceProductId,
    'bressol_reco_type' => (string) $rec['type'],
], get_permalink($rec['product_id']));
?>
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

            document.addEventListener('click', function(e){
  const a = e.target.closest('a.bressol-reco-link');
  if (!a) return;

  e.preventDefault();

  const payload = new URLSearchParams();
  payload.append('action', 'bressol_reco_click');
  payload.append('source_product_id', '<?php echo esc_js((string)$sourceProductId); ?>');
  payload.append('recommended_product_id', a.dataset.recoProductId || '');
  payload.append('reco_type', a.dataset.recoType || '');

  fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
    body: payload.toString()
  }).catch(function(){ /* ignore */ })
    .finally(function(){
      window.location.href = a.href;
    });
}, {capture:true});
          })();
        </script>
        <?php
    }

    public function ajaxRecoClick(): void
{
    if (!function_exists('WC') || !WC()->session) {
        wp_send_json_error(['message' => 'No session'], 400);
    }

    $source = isset($_POST['source_product_id']) ? (int) $_POST['source_product_id'] : 0;
    $target = isset($_POST['recommended_product_id']) ? (int) $_POST['recommended_product_id'] : 0;
    $type   = isset($_POST['reco_type']) ? sanitize_text_field((string) $_POST['reco_type']) : '';

    if ($source <= 0 || $target <= 0) {
        wp_send_json_error(['message' => 'Invalid payload'], 400);
    }

    WC()->session->set('bressol_datalayer_reco_click', [
        'context' => 'pdp',
        'source_product_id' => (string) $source,
        'recommended_product_id' => (string) $target,
        'reco_type' => $type ?: null,
    ]);

    wp_send_json_success(['ok' => true]);
}

    private function getRecommendationsForFamily(string $family): array
    {
        // MVP basado en el catálogo mínimo actual
        if ($family === 'oil') {
            return [
                [
                    'product_id' => self::PACK_COCINA_ID,
                    'type' => 'upsell_pack',
                    'reason' => 'Pack recomendado para cocina: combina aceite con complementos.',
                ],
                [
                    'product_id' => self::SALT_ID,
                    'type' => 'cross_sell',
                    'reason' => 'Complemento ideal para el aceite.',
                ],
                [
                    'product_id' => self::VINEGAR_ID,
                    'type' => 'cross_sell',
                    'reason' => 'Completa tu set con vinagre artesano.',
                ],
            ];
        }

        if ($family === 'drinks') {
            return [
                [
                    'product_id' => self::OLIVES_ID,
                    'type' => 'cross_sell',
                    'reason' => 'Perfecto para acompañar bebidas (borrel).',
                ],
                [
                    'product_id' => self::TAPENADE_ID,
                    'type' => 'cross_sell',
                    'reason' => 'Añade un aperitivo gourmet para tu borrel.',
                ],
            ];
        }

        return [];
    }

    private function detectFamilyByCategory(int $productId): string
    {
        $terms = get_the_terms($productId, 'product_cat');
        if (!is_array($terms)) {
            return 'other';
        }

        $slugs = array_map(fn($t) => strtolower((string)$t->slug), $terms);

        if (in_array('oil', $slugs, true)) return 'oil';
        if (in_array('drinks', $slugs, true)) return 'drinks';
        if (in_array('tapenade', $slugs, true) || in_array('olives', $slugs, true)) return 'borrel';
        if (in_array('salt', $slugs, true) || in_array('vinegar', $slugs, true)) return 'oil';

        return 'other';
    }
}