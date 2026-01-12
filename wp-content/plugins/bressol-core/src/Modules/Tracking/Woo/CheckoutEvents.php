<?php
declare(strict_types=1);

namespace Bressol\Modules\Tracking\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class CheckoutEvents
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'pushBeginCheckout'], 5);
    }

    public function pushBeginCheckout(): void
    {
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }

        if (function_exists('is_order_received_page') && is_order_received_page()) {
            return;
        }

        if (!function_exists('WC') || !WC()->cart || !WC()->session) {
            return;
        }

        // Evitar duplicado por sesión
        if (WC()->session->get('bressol_datalayer_begin_checkout_sent')) {
            return;
        }
        WC()->session->set('bressol_datalayer_begin_checkout_sent', true);

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
        echo "window.dataLayer.push(Object.assign({event:'begin_checkout'}, " . wp_json_encode($payload) . "));";
        echo "</script>\n";
    }
}