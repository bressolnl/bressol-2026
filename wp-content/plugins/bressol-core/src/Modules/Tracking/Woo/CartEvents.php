<?php
declare(strict_types=1);

namespace Bressol\Modules\Tracking\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class CartEvents
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'pushViewCart'], 5);
        add_action('woocommerce_remove_cart_item', [$this, 'queueRemoveFromCart'], 10, 2);
    }

    public function pushViewCart(): void
    {
        if (!function_exists('is_cart') || !is_cart()) {
            return;
        }

        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        $items = [];
        foreach (WC()->cart->get_cart() as $cartItem) {
            $product = $cartItem['data'] ?? null;
            if (!$product || !is_object($product)) {
                continue;
            }

            $items[] = [
                'item_id'   => (string) $product->get_id(),
                'item_name' => method_exists($product, 'get_name') ? $product->get_name() : '',
                'quantity'  => (int) ($cartItem['quantity'] ?? 1),
                'price'     => function_exists('wc_get_price_to_display') ? (float) wc_get_price_to_display($product) : null,
                'currency'  => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : null,
            ];
        }

        $payload = [
            'ecommerce' => [
                'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : null,
                'value'    => (float) WC()->cart->get_total('edit'),
                'items'    => $items,
            ],
        ];

        echo "\n<script>";
        echo "window.dataLayer = window.dataLayer || [];";
        echo "window.dataLayer.push(Object.assign({event:'view_cart'}, " . wp_json_encode($payload) . "));";
        echo "</script>\n";
    }

    public function queueRemoveFromCart(string $cart_item_key, $cart): void
    {
        if (!function_exists('WC') || !WC()->session || !WC()->cart) {
            return;
        }

        // Capturar datos del ítem antes de que desaparezca
        $cartItem = WC()->cart->get_cart_item($cart_item_key);
        if (!$cartItem) {
            return;
        }

        $product = $cartItem['data'] ?? null;
        if (!$product || !is_object($product)) {
            return;
        }

        $payload = [
            'ecommerce' => [
                'items' => [
                    [
                        'item_id'   => (string) $product->get_id(),
                        'item_name' => method_exists($product, 'get_name') ? $product->get_name() : '',
                        'quantity'  => (int) ($cartItem['quantity'] ?? 1),
                        'price'     => function_exists('wc_get_price_to_display') ? (float) wc_get_price_to_display($product) : null,
                        'currency'  => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : null,
                    ],
                ],
            ],
        ];

        // Cola en sesión para imprimirlo en el siguiente render
        WC()->session->set('bressol_datalayer_remove_from_cart', $payload);
    }
}