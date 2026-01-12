<?php
declare(strict_types=1);

namespace Bressol\Modules\Tracking\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class WooEvents
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'pushViewItem'], 5);
        add_action('woocommerce_add_to_cart', [$this, 'pushAddToCart'], 10, 6);
    }

    public function pushViewItem(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        $product = wc_get_product(get_the_ID());
        if (!$product) {
            return;
        }

        $payload = [
            'ecommerce' => [
                'items' => [
                    [
                        'item_id'   => (string) $product->get_id(),
                        'item_name' => $product->get_name(),
                        'price'     => (float) wc_get_price_to_display($product),
                        'currency'  => get_woocommerce_currency(),
                    ],
                ],
            ],
        ];

        echo "\n<script>";
        echo "window.dataLayer = window.dataLayer || [];";
        echo "window.dataLayer.push(Object.assign({event:'view_item'}, " . wp_json_encode($payload) . "));";
        echo "</script>\n";
    }

    /**
     * @param string $cart_item_key
     * @param int    $product_id
     * @param int    $quantity
     * @param int    $variation_id
     * @param array  $variation
     * @param array  $cart_item_data
     */
    public function pushAddToCart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data): void
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $id = $variation_id ?: $product_id;
        $product = wc_get_product($id);
        if (!$product) {
            return;
        }

        $payload = [
            'ecommerce' => [
                'items' => [
                    [
                        'item_id'   => (string) $product->get_id(),
                        'item_name' => $product->get_name(),
                        'quantity'  => (int) $quantity,
                        'price'     => (float) wc_get_price_to_display($product),
                        'currency'  => get_woocommerce_currency(),
                    ],
                ],
            ],
        ];

        // Se ejecuta en backend; lo registramos en sesión para imprimirlo en la siguiente carga.
        WC()->session->set('bressol_datalayer_add_to_cart', $payload);
    }
}