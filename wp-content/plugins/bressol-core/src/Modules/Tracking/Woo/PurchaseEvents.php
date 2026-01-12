<?php
declare(strict_types=1);

namespace Bressol\Modules\Tracking\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class PurchaseEvents
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'pushPurchase'], 5);
    }

    public function pushPurchase(): void
    {
        if (!function_exists('is_order_received_page') || !is_order_received_page()) {
            return;
        }

        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $orderId = $this->getOrderIdFromRequest();
        if (!$orderId) {
            return;
        }

        // Evitar duplicados por sesión + order_id
        $sentKey = 'bressol_datalayer_purchase_sent_' . $orderId;
        if (WC()->session->get($sentKey)) {
            return;
        }
        WC()->session->set($sentKey, true);

        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $items[] = [
                'item_id'   => (string) $product->get_id(),
                'item_name' => $product->get_name(),
                'quantity'  => (int) $item->get_quantity(),
                'price'     => (float) $order->get_item_total($item, false, true),
                'currency'  => $order->get_currency(),
            ];
        }

        $payload = [
            'ecommerce' => [
                'transaction_id' => (string) $order->get_id(),
                'currency'       => $order->get_currency(),
                'value'          => (float) $order->get_total(),
                'tax'            => (float) $order->get_total_tax(),
                'shipping'       => (float) $order->get_shipping_total(),
                'coupon'         => $order->get_coupon_codes() ? implode(',', $order->get_coupon_codes()) : null,
                'items'          => $items,
            ],
        ];

        echo "\n<script>";
        echo "window.dataLayer = window.dataLayer || [];";
        echo "window.dataLayer.push(Object.assign({event:'purchase'}, " . wp_json_encode($payload) . "));";
        echo "</script>\n";
    }

    private function getOrderIdFromRequest(): ?int
    {
        // En WooCommerce, la URL order-received suele incluir order_id como key.
        // Ejemplo: /checkout/order-received/123/?key=wc_order_xxx
        $orderId = get_query_var('order-received');

        if (is_numeric($orderId)) {
            return (int) $orderId;
        }

        // Fallback: si no hay query_var, intentar parsear de la URL.
        if (!empty($_GET['order_id']) && is_numeric($_GET['order_id'])) {
            return (int) $_GET['order_id'];
        }

        return null;
    }
}