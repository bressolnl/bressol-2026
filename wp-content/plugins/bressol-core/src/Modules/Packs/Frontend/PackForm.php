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

            $min = (int) ($slot['min'] ?? 0);
            $max = (int) ($slot['max'] ?? 1);

            echo '<div style="margin:10px 0;">';
            echo '<label style="display:block;font-weight:600;margin-bottom:6px;">'
                . esc_html($label)
                . ($required ? ' <span style="color:#b00;">*</span>' : '')
                . '</label>';

            // Caso 1: selección única (max <= 1) -> select como antes
            if ($max <= 1) {
                echo '<select name="bressol_pack[' . esc_attr($key) . ']">';
                echo '<option value="">-- Selecciona --</option>';

                foreach (($slot['options'] ?? []) as $opt) {
                    $pid = (int) ($opt['product_id'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }

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
                continue;
            }

            // Caso 2: selección múltiple con cantidades (max > 1)
            echo '<div style="display:grid;gap:8px;">';

            foreach (($slot['options'] ?? []) as $opt) {
                $pid = (int) ($opt['product_id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }

                $optLabel = (string) ($opt['label'] ?? ('Product ' . $pid));
                $surcharge = (float) ($opt['surcharge'] ?? 0);

                $suffix = $surcharge > 0 ? (' (+' . $surcharge . '€ / unidad)') : '';

                echo '<div style="display:flex;gap:10px;align-items:center;justify-content:space-between;border:1px solid #eee;padding:8px;">';
                echo '<div>' . esc_html($optLabel . $suffix) . '</div>';

                // Importante: nombre como array por product_id para permitir repetir (qty)
                echo '<input type="number" min="0" max="' . esc_attr((string) $max) . '" value="0" '
                    . 'name="bressol_pack[' . esc_attr($key) . '][' . esc_attr((string) $pid) . ']" '
                    . 'style="width:80px;" />';

                echo '</div>';
            }

            echo '</div>';
            echo '<small>Selecciona entre ' . esc_html((string) $min) . ' y ' . esc_html((string) $max) . ' unidades en total.</small>';
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