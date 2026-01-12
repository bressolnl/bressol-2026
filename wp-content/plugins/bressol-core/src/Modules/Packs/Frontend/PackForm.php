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
        add_action('woocommerce_before_add_to_cart_button', [$this, 'renderPackFields'], 9);
    }

    public function renderPackFields(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $def = $this->getPackDefinition((int) $product->get_id());
        if (!$def) {
            return; // no es pack
        }

        echo '<div class="bressol-pack" style="padding:12px;border:1px solid #ddd;margin:12px 0;">';
        echo '<h3 style="margin:0 0 8px 0;">Configura tu pack</h3>';

        foreach (($def['slots'] ?? []) as $slot) {
            $key = (string) ($slot['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $label = (string) ($slot['label'] ?? $key);
            $required = !empty($slot['required']);

            echo '<div style="margin:10px 0;">';
            echo '<label style="display:block;font-weight:600;margin-bottom:6px;">'
                . esc_html($label)
                . ($required ? ' <span style="color:#b00;">*</span>' : '')
                . '</label>';

            // MVP: max=1 => select. Más adelante: checkbox multiselección.
            echo '<select name="bressol_pack[' . esc_attr($key) . ']">';
            echo '<option value="">-- Selecciona --</option>';

            foreach (($slot['options'] ?? []) as $opt) {
                $pid = (int) ($opt['product_id'] ?? 0);
                if ($pid <= 0) continue;

                $optLabel = (string) ($opt['label'] ?? ('Product ' . $pid));
                $surcharge = (float) ($opt['surcharge'] ?? 0);

                $value = $pid . '|' . $surcharge;

                $suffix = $surcharge > 0 ? (' (+' . $surcharge . '€)') : '';
                echo '<option value="' . esc_attr($value) . '">'
                    . esc_html($optLabel . $suffix)
                    . '</option>';
            }

            echo '</select>';
            echo '</div>';
        }

        echo '</div>';
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