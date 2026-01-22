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
            if ($key === '') {
                continue;
            }

            $required = !empty($slot['required']);
            $min = (int) ($slot['min'] ?? 0);
            $max = (int) ($slot['max'] ?? 1);

            // Slot simple (max <= 1)
            if ($max <= 1) {
                $value = isset($input[$key]) ? (string) $input[$key] : '';
                if ($required && $value === '') {
                    wc_add_notice('Falta seleccionar: ' . ($slot['label'] ?? $key), 'error');
                    return false;
                }
                continue;
            }

            // Slot múltiple (max > 1) -> array [productId => qty]
            $slotValues = $input[$key] ?? [];
            if (!is_array($slotValues)) {
                $slotValues = [];
            }

            $totalQty = 0;
            foreach ($slotValues as $pid => $qtyVal) {
                $q = (int) $qtyVal;
                if ($q < 0) $q = 0;
                $totalQty += $q;
            }

            if ($required && $totalQty < $min) {
                wc_add_notice(
                    'Debes seleccionar al menos ' . $min . ' en: ' . ($slot['label'] ?? $key),
                    'error'
                );
                return false;
            }

            if ($totalQty > $max) {
                wc_add_notice(
                    'Has seleccionado más de ' . $max . ' en: ' . ($slot['label'] ?? $key),
                    'error'
                );
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

        // 1) Intentar input desde PDP
        $input = $_POST['bressol_pack'] ?? null;

        // 2) Fallback: viene desde modal (slot => pid) o (slot => [pid=>qty])
        if (!is_array($input) || empty($input)) {
            $cfg = $cartItemData['bressol_pack_config'] ?? null;
            if (is_array($cfg) && !empty($cfg)) {
                $input = $cfg;
            }
        }

        if (!is_array($input) || empty($input)) {
            return $cartItemData;
        }

        $selections = [];
        $surchargeTotal = 0.0;

        // Map product_id -> surcharge por slot
        $surchargeMapBySlot = $this->buildSurchargeMap($def);

        foreach ($def['slots'] ?? [] as $slot) {
            $slotKey = (string) ($slot['key'] ?? '');
            if ($slotKey === '') {
                continue;
            }

            $max = (int) ($slot['max'] ?? 1);

            // Slot simple
            if ($max <= 1) {
                $rawVal = $input[$slotKey] ?? '';
                if ($rawVal === '' || $rawVal === null) {
                    continue;
                }

                $pid = 0;
                $surcharge = 0.0;

                // Caso A: PDP -> "pid|surcharge"
                if (is_string($rawVal) && strpos($rawVal, '|') !== false) {
                    [$pidStr, $sStr] = array_pad(explode('|', $rawVal), 2, '0');
                    $pid = (int) $pidStr;
                    $surcharge = (float) $sStr;
                } else {
                    // Caso B: modal -> pid (int/string num)
                    $pid = (int) $rawVal;
                    $surcharge = (float) ($surchargeMapBySlot[$slotKey][$pid] ?? 0.0);
                }

                if ($pid <= 0) {
                    continue;
                }

                $selections[$slotKey] = [
                    [
                        'product_id' => $pid,
                        'qty'        => 1,
                        'surcharge'  => $surcharge,
                    ],
                ];

                $surchargeTotal += $surcharge;
                continue;
            }

            // Slot múltiple: array pid=>qty
            $slotValues = $input[$slotKey] ?? [];
            if (!is_array($slotValues)) {
                $slotValues = [];
            }

            foreach ($slotValues as $pidStr => $qtyVal) {
                $pid = (int) $pidStr;
                $q = (int) $qtyVal;

                if ($pid <= 0 || $q <= 0) {
                    continue;
                }

                $surcharge = (float) ($surchargeMapBySlot[$slotKey][$pid] ?? 0.0);

                $selections[$slotKey][] = [
                    'product_id' => $pid,
                    'qty'        => $q,
                    'surcharge'  => $surcharge,
                ];

                $surchargeTotal += ($q * $surcharge);
            }
        }

        $cartItemData['bressol_pack'] = [
            'selections'      => $selections,
            'surcharge_total' => $surchargeTotal,
        ];

        // Evita que Woo agrupe packs con selecciones distintas
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

            $product->set_price($base + $surcharge);
        }
    }

    public function displayPackSelectionInCart(array $itemData, array $cartItem): array
    {
        $selections = $cartItem['bressol_pack']['selections'] ?? null;
        if (!is_array($selections) || empty($selections)) {
            return $itemData;
        }

        foreach ($selections as $slotKey => $lines) {
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $pid = (int) ($line['product_id'] ?? 0);
                $qty = (int) ($line['qty'] ?? 0);

                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }

                $p = wc_get_product($pid);
                $name = $p ? $p->get_name() : ('Product ' . $pid);

                $itemData[] = [
                    'key'   => 'Pack: ' . $slotKey,
                    'value' => $name . ' ×' . $qty,
                ];
            }
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

    private function buildSurchargeMap(array $def): array
    {
        $map = [];

        foreach (($def['slots'] ?? []) as $slot) {
            $slotKey = (string) ($slot['key'] ?? '');
            if ($slotKey === '') {
                continue;
            }

            foreach (($slot['options'] ?? []) as $opt) {
                $pid = (int) ($opt['product_id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }

                $map[$slotKey][$pid] = (float) ($opt['surcharge'] ?? 0.0);
            }
        }

        return $map;
    }

    private function getPackDefinition(int $productId): ?array
    {
        $raw = (string) get_post_meta($productId, self::META_KEY, true);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
