<?php
declare(strict_types=1);

namespace Bressol\Modules\Packs\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductCardInfo
{
    private const CACHE_GROUP = 'bressol_card_info';
    private const CACHE_TTL = 600;

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_filter('woocommerce_post_class', [$this, 'addProductClass'], 10, 2);
        add_action('woocommerce_before_shop_loop_item_title', [$this, 'renderInfoButton'], 6);
    }

    public function enqueueAssets(): void
    {
        if (is_admin()) {
            return;
        }

        $js = plugins_url(
            'assets/js/bressol-card-info.js',
            WP_PLUGIN_DIR . '/bressol-core/bressol-core.php'
        );
        $css = plugins_url(
            'assets/css/bressol-card-info.css',
            WP_PLUGIN_DIR . '/bressol-core/bressol-core.php'
        );

        wp_enqueue_script('bressol-card-info', $js, [], '0.1.0', true);
        wp_enqueue_style('bressol-card-info', $css, [], '0.1.0');

        wp_localize_script('bressol-card-info', 'bressolCardInfo', [
            'restUrl' => esc_url_raw(rest_url('bressol/v1/product-card/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    public function registerRoutes(): void
    {
        register_rest_route('bressol/v1', '/product-card/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getProductCard'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => static function ($param): bool {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
            ],
        ]);
    }

    public function getProductCard(\WP_REST_Request $request)
    {
        $productId = (int) $request->get_param('id');
        if ($productId <= 0) {
            return new \WP_Error('bressol_invalid_product', 'Invalid product id', ['status' => 400]);
        }

        $cacheKey = 'product_card_' . $productId;
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if (is_array($cached)) {
            return rest_ensure_response($cached);
        }

        $product = wc_get_product($productId);
        if (!$product || $product->get_status() !== 'publish') {
            return new \WP_Error('bressol_not_found', 'Product not found', ['status' => 404]);
        }

        $short = $product->get_short_description();
        if ($short === '') {
            $short = wp_trim_words(wp_strip_all_tags($product->get_description()), 28, '...');
        }

        $imageId = (int) $product->get_image_id();
        $image = $imageId ? wp_get_attachment_image_url($imageId, 'woocommerce_thumbnail') : '';

        $payload = [
            'id' => $productId,
            'title' => $product->get_name(),
            'permalink' => get_permalink($productId),
            'image' => $image ?: '',
            'short_description_html' => wp_kses_post($short),
        ];

        wp_cache_set($cacheKey, $payload, self::CACHE_GROUP, self::CACHE_TTL);

        return rest_ensure_response($payload);
    }

    public function addProductClass(array $classes, $product): array
    {
        $classes[] = 'bressol-card-info-target';
        return $classes;
    }

    public function renderInfoButton(): void
    {
        global $product;
        if (!$product) {
            return;
        }
        $productId = (int) $product->get_id();
        if ($productId <= 0) {
            return;
        }

        static $index = 0;
        $index++;
        $controlId = 'bressol-card-info-' . $productId . '-' . $index;

        echo '<button type="button" class="bressol-card-info-trigger" data-product-id="'
            . esc_attr((string) $productId)
            . '" data-context="loop" aria-expanded="false" aria-controls="'
            . esc_attr($controlId)
            . '" aria-label="'
            . esc_attr__('Productinfo', 'bressol-core')
            . '">i</button>';
    }
}
