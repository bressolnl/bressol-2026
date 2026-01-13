<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations;

use Bressol\Core\ModuleInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class RecommendationsModule implements ModuleInterface
{
    public function register(): void
    {
        if (is_admin()) {
            return;
        }

        if (!class_exists('\WooCommerce')) {
            return;
        }

        // Ocultar bloques nativos de Woo (related/upsells) para evitar confusión
        add_action('wp', function (): void {
            remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
            remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
        }, 20);

        (new \Bressol\Modules\Recommendations\Frontend\RecoClickCapture())->register();
        (new \Bressol\Modules\Recommendations\Frontend\ProductPageRecommendations())->register();
    }
}