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
        // No cargamos tracking en admin.
        if (is_admin()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_head', [$this, 'printBasePageViewEvent'], 1);
        add_action('wp_head', [$this, 'printQueuedWooEvents'], 6);

        // Registrar eventos WooCommerce (si WooCommerce está activo)
        if (class_exists('\WooCommerce')) {
            (new \Bressol\Modules\Tracking\Woo\WooEvents())->register();
            (new \Bressol\Modules\Tracking\Woo\CartEvents())->register();
            (new \Bressol\Modules\Tracking\Woo\CheckoutEvents())->register();
            (new \Bressol\Modules\Tracking\Woo\PurchaseEvents())->register();
        }
    }

    public function enqueueAssets(): void
    {
        $handle = 'bressol-core-tracking';

        // Base robusta: usar el archivo principal del plugin por ruta estable.
        $src = plugins_url(
            'src/Modules/Tracking/assets/tracking.js',
            WP_PLUGIN_DIR . '/bressol-core/bressol-core.php'
        );

        // Usa [] si tu tracking.js no depende de jQuery.
        wp_enqueue_script($handle, $src, [], '0.1.0', true);
    }

    public function printBasePageViewEvent(): void
    {
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

    public function printQueuedWooEvents(): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        // 1) guided upsell add_to_cart (cola) — contexto
        $guided = WC()->session->get('bressol_datalayer_guided_upsell');
        if ($guided) {
            WC()->session->__unset('bressol_datalayer_guided_upsell');

            echo "\n<script>";
            echo "window.dataLayer = window.dataLayer || [];";
            echo "window.dataLayer.push(Object.assign({event:'guided_upsell_add_to_cart'}, " . wp_json_encode($guided) . "));";
            echo "</script>\n";
        }

        // 2) reco click (cola)
        $recoClick = WC()->session->get('bressol_datalayer_reco_click');
        if ($recoClick) {
            WC()->session->__unset('bressol_datalayer_reco_click');

            echo "\n<script>";
            echo "window.dataLayer = window.dataLayer || [];";
            echo "window.dataLayer.push(Object.assign({event:'bressol_reco_click'}, " . wp_json_encode($recoClick) . "));";
            echo "</script>\n";
        }

        // 3) add_to_cart (cola)
        $addPayload = WC()->session->get('bressol_datalayer_add_to_cart');
        if ($addPayload) {
            WC()->session->__unset('bressol_datalayer_add_to_cart');

            echo "\n<script>";
            echo "window.dataLayer = window.dataLayer || [];";
            echo "window.dataLayer.push(Object.assign({event:'add_to_cart'}, " . wp_json_encode($addPayload) . "));";
            echo "</script>\n";
        }

        // 4) remove_from_cart (cola)
        $removePayload = WC()->session->get('bressol_datalayer_remove_from_cart');
        if ($removePayload) {
            WC()->session->__unset('bressol_datalayer_remove_from_cart');

            echo "\n<script>";
            echo "window.dataLayer = window.dataLayer || [];";
            echo "window.dataLayer.push(Object.assign({event:'remove_from_cart'}, " . wp_json_encode($removePayload) . "));";
            echo "</script>\n";
        }
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