<?php
declare(strict_types=1);

namespace Bressol\Modules\Tracking;

use Bressol\Core\ModuleInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class TrackingModule implements ModuleInterface
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_head', [$this, 'printBasePageViewEvent'], 1);
    }

    public function enqueueAssets(): void
    {
        $handle = 'bressol-core-tracking';
        $src = plugins_url('src/Modules/Tracking/assets/tracking.js', dirname(__DIR__, 3) . '/bressol-core.php');

        // En Windows symlink + plugins_url a veces es delicado.
        // Alternativa robusta: construir URL desde plugin base file real:
        $src = plugins_url('src/Modules/Tracking/assets/tracking.js', WP_PLUGIN_DIR . '/bressol-core/bressol-core.php');

        wp_enqueue_script($handle, $src, [], '0.1.0', true);
    }

    public function printBasePageViewEvent(): void
    {
        // No queremos meter nada fuera de dataLayer.
        // Esto es un “evento base” propio, útil para GTM triggers.
        $payload = [
            'page_type' => $this->getPageType(),
            'language'  => determine_locale(),
            'currency'  => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : null,
        ];

        $json = wp_json_encode($payload);

        echo "\n<script>";
        echo "window.dataLayer = window.dataLayer || [];";
        echo "window.dataLayer.push(Object.assign({event:'bressol_page_view'}, {$json}));";
        echo "</script>\n";
    }

    private function getPageType(): string
    {
        if (function_exists('is_product') && is_product()) {
            return 'product';
        }
        if (function_exists('is_shop') && is_shop()) {
            return 'shop';
        }
        if (function_exists('is_cart') && is_cart()) {
            return 'cart';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }
        if (is_front_page()) {
            return 'home';
        }
        return 'other';
    }
}