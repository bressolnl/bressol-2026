<?php
declare(strict_types=1);

namespace Bressol\Modules\Packs\Woo;

if (!defined('ABSPATH')) {
    exit;
}

final class PackCart
{
    private const META_KEY = '_bressol_pack_definition';

    public function register(): void
    {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateBeforeAddToCart'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'capturePackSelection'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyPackSurcharges'], 10, 1);
        add_filter('woocommerce_get_item_data', [$this, 'displayPackSelectionInCart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'storePackSelectionInOrder'], 10, 4);
    }

    public function validateBeforeAddToCart(bool $passed, int $productId, int $qty): bool
    {
        $def = $this->getPackDefinition($productId);
        if (!$def) {
            return $passed;
        }

        $slots = $def['slots'] ?? [];
        $input = $_POST['bressol_pack'] ?? [];

        foreach ($slots as $slot) {
            $key = (string) ($slot['key'] ?? '');
            if ($key === '') continue;

            $required = !empty($slot['required']);
            $value = isset($input[$key]) ? (string) $input[$key] : '';

            if ($required && $value === '') {
                wc_add_notice('Falta seleccionar: ' . ($slot['label'] ?? $key), 'error');
                return false;
            }
        }

        return $passed;
    }

    public function capturePackSelection(array $cartItemData, int $productId): array
    {
        $def = $this->getPackDefinition($productId);
        if (!$def) {
            return $cartItemData;
        }

        $input = $_POST['bressol_pack'] ?? [];
        if (!is_array($input)) {
            return $cartItemData;
        }

        $selections = [];
        $surchargeTotal = 0.0;

        foreach ($input as $slotKey => $raw) {
            $raw = (string) $raw;
            if ($raw === '') continue;

            // value = "productId|surcharge"
            [$pidStr, $sStr] = array_pad(explode('|', $raw), 2, '0');
            $pid = (int) $pidStr;
            $surcharge = (float) $sStr;

            if ($pid <= 0) continue;

            $selections[(string) $slotKey] = [
                'product_id' => $pid,
                'surcharge'  => $surcharge,
            ];

            $surchargeTotal += $surcharge;
        }

        $cartItemData['bressol_pack'] = [
            'selections' => $selections,
            'surcharge_total' => $surchargeTotal,
        ];

        // Para que Woo distinga dos packs con selecciones distintas (evita que se agrupen)
        $cartItemData['bressol_pack_hash'] = md5(wp_json_encode($cartItemData['bressol_pack']));

        return $cartItemData;
    }

    public function applyPackSurcharges($cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cartItem) {
            if (empty($cartItem['bressol_pack']) || empty($cartItem['data'])) {
                continue;
            }

            $product = $cartItem['data'];
            $base = (float) $product->get_price();

            $surcharge = (float) ($cartItem['bressol_pack']['surcharge_total'] ?? 0);

            // Ajustar precio final del pack
            $product->set_price($base + $surcharge);
        }
    }

    public function displayPackSelectionInCart(array $itemData, array $cartItem): array
    {
        if (empty($cartItem['bressol_pack']['selections'])) {
            return $itemData;
        }

        foreach ($cartItem['bressol_pack']['selections'] as $slotKey => $sel) {
            $pid = (int) ($sel['product_id'] ?? 0);
            if ($pid <= 0) continue;

            $p = wc_get_product($pid);
            $name = $p ? $p->get_name() : ('Product ' . $pid);

            $itemData[] = [
                'key'   => 'Pack: ' . $slotKey,
                'value' => $name,
            ];
        }

        return $itemData;
    }

    public function storePackSelectionInOrder($item, $cartItemKey, $values, $order): void
    {
        if (empty($values['bressol_pack'])) {
            return;
        }

        $item->add_meta_data('_bressol_pack', $values['bressol_pack']);
    }

    private function getPackDefinition(int $productId): ?array
    {
        $raw = (string) get_post_meta($productId, self::META_KEY, true);
        if ($raw === '') return null;

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}