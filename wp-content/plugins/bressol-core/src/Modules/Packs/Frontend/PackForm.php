<?php
declare(strict_types=1);

namespace Bressol\Modules\Packs\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class PackForm
{
    private const META_KEY = '_bressol_pack_definition';

    public function register(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render'], 9);
    }

    public function render(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $productId = (int) $product->get_id();

        $json = (string) get_post_meta($productId, self::META_KEY, true);
        if ($json === '') {
            return;
        }

        $definition = json_decode($json, true);
        if (!is_array($definition) || empty($definition['slots']) || !is_array($definition['slots'])) {
            return;
        }

        // Prefill (upgrade): leer una vez
        $prefillSlot = isset($_GET['bressol_prefill_slot'])
            ? sanitize_text_field((string) $_GET['bressol_prefill_slot'])
            : '';

        $prefillPid = isset($_GET['bressol_prefill_product_id'])
            ? (int) $_GET['bressol_prefill_product_id']
            : 0;

        echo '<div class="bressol-pack-form" style="margin:12px 0;padding:12px;border:1px solid #eee;">';
        echo '<h4 style="margin:0 0 10px 0;">Personaliza tu pack</h4>';

        foreach ($definition['slots'] as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $key = isset($slot['key']) ? (string) $slot['key'] : '';
            if ($key === '') {
                continue;
            }

            $label = isset($slot['label']) ? (string) $slot['label'] : $key;
            $options = isset($slot['options']) && is_array($slot['options']) ? $slot['options'] : [];

            if (empty($options)) {
                continue;
            }

            // Default recomendado: primera option del JSON
            $selectedPid = (int) ($options[0]['product_id'] ?? 0);

            // Override por prefill si coincide slot y el producto existe en options
            if ($prefillPid > 0 && $prefillSlot !== '' && $key === $prefillSlot) {
                foreach ($options as $opt) {
                    $pidOpt = (int) ($opt['product_id'] ?? 0);
                    if ($pidOpt === $prefillPid) {
                        $selectedPid = $prefillPid;
                        break;
                    }
                }
            }

            echo '<div style="margin:10px 0;">';
            echo '<label style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html($label) . '</label>';

            echo '<select name="bressol_pack[' . esc_attr($key) . ']" style="width:100%;padding:8px;">';

            foreach ($options as $opt) {
                if (!is_array($opt)) {
                    continue;
                }

                $pid = (int) ($opt['product_id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }

                $p = wc_get_product($pid);
                if (!$p) {
                    continue;
                }

                $surcharge = isset($opt['surcharge']) ? (float) $opt['surcharge'] : 0.0;

                $optLabel = isset($opt['label']) && $opt['label'] !== ''
                    ? (string) $opt['label']
                    : $p->get_name();

                if ($surcharge > 0) {
                    $optLabel .= ' (+' . strip_tags(wc_price($surcharge)) . ')';
                }

                echo '<option value="' . esc_attr((string) $pid) . '"' . selected($selectedPid, $pid, false) . '>';
                echo esc_html($optLabel);
                echo '</option>';
            }

            echo '</select>';
            echo '</div>';
        }

        echo '</div>';
    }
}