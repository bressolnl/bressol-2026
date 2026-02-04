<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class InternalOrderService
{
    public const META_INTERNAL = '_bressol_internal_order';
    public const META_INTERNAL_INVALID = '_bressol_internal_invalid';
    public const META_REASON = '_bressol_internal_reason';
    public const META_STOCK_REDUCED = '_bressol_stock_reduced';

    /**
     * @param array<int, array{product_id:int,qty:int}> $items
     */
    public function create_sampling_open_order(int $eventId, int $operatorUserId, array $items): int
    {
        if ($eventId <= 0) {
            throw new \InvalidArgumentException('event_id must be > 0');
        }

        if (!function_exists('wc_create_order')) {
            throw new \RuntimeException('WooCommerce not available');
        }

        $validatedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('items must be arrays with product_id and qty');
            }
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
            if ($productId <= 0) {
                throw new \InvalidArgumentException('product_id must be > 0');
            }
            if ($qty <= 0) {
                throw new \InvalidArgumentException('qty must be > 0');
            }
            $product = wc_get_product($productId);
            if (!$product) {
                throw new \InvalidArgumentException('product not found: ' . $productId);
            }
            $validatedItems[] = [
                'product' => $product,
                'qty' => $qty,
            ];
        }

        if ($validatedItems === []) {
            throw new \InvalidArgumentException('items cannot be empty');
        }

        $order = wc_create_order();
        if (!$order instanceof \WC_Order) {
            throw new \RuntimeException('failed to create order');
        }

        foreach ($validatedItems as $item) {
            $result = $order->add_product($item['product'], $item['qty']);
            if (!$result) {
                throw new \RuntimeException('failed to add product to order');
            }
        }

        $order->set_currency(get_woocommerce_currency());
        $order->set_status(InternalOrderStatus::STATUS);

        foreach ($order->get_items() as $orderItem) {
            if (method_exists($orderItem, 'set_subtotal')) {
                $orderItem->set_subtotal(0);
            }
            if (method_exists($orderItem, 'set_total')) {
                $orderItem->set_total(0);
            }
            if (method_exists($orderItem, 'set_taxes')) {
                $orderItem->set_taxes(['total' => [], 'subtotal' => []]);
            }
            $orderItem->save();
        }

        // We still calculate totals to keep WooCommerce state consistent,
        // but we force all totals to zero for internal sampling orders.
        $order->calculate_totals(false);
        foreach ($order->get_items('tax') as $taxItemId => $taxItem) {
            $order->remove_item((int) $taxItemId);
        }
        $order->set_discount_total(0);
        $order->set_shipping_total(0);
        $order->set_cart_tax(0);
        $order->set_shipping_tax(0);
        $order->set_total(0);

        $order->update_meta_data(self::META_INTERNAL, '1');
        $order->update_meta_data(self::META_REASON, 'sampling_open');
        $order->update_meta_data('_bressol_event_id', $eventId);
        $order->update_meta_data('_bressol_pos_operator_id', $operatorUserId);

        $order->save();

        return (int) $order->get_id();
    }

    public function reduce_stock_once(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            throw new \RuntimeException('order not found');
        }

        $alreadyReduced = (string) $order->get_meta(self::META_STOCK_REDUCED) === '1';
        if ($alreadyReduced) {
            return;
        }

        // Reduce stock after the order is fully saved to avoid Woo hooks re-triggering.
        wc_reduce_stock_levels($order->get_id());
        $order->update_meta_data(self::META_STOCK_REDUCED, '1');
        $order->save();
    }

    public function mark_internal_invalid(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            return;
        }

        // We keep the order as an explicit invalid internal record for auditing.
        $order->update_meta_data(self::META_INTERNAL_INVALID, '1');
        $order->save();
    }
}
