<?php
declare(strict_types=1);

namespace Bressol\Modules\Packs\Frontend;

use Bressol\Modules\Inventory\Services\AuditLogger as InventoryAuditLogger;
use Bressol\Modules\Inventory\Services\CacheService as InventoryCacheService;
use Bressol\Modules\Inventory\Services\SellableService;

if (!defined('ABSPATH')) {
    exit;
}

final class PackForm
{
    private const META_KEY = '_bressol_pack_definition';

    public function register(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render'], 9);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
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

        $inventoryService = SellableService::build_default(new InventoryCacheService(), new InventoryAuditLogger());
        $hintLabel = 'Disponible según selección';
        if (method_exists($inventoryService, 'get_pack_sellable')) {
            $sellableHint = $inventoryService->get_pack_sellable($productId);
            $minValue = (int) ($sellableHint['sellable'] ?? 0);
            if ($minValue >= 1000000) {
                $hintLabel = 'Stock orientativo: disponible';
            } elseif ($minValue > 0) {
                $hintLabel = 'Stock orientativo: ' . $minValue . ' packs disponibles';
            }
        }

        // Prefill (upgrade): leer una vez
        $prefillSlot = isset($_GET['bressol_prefill_slot'])
            ? sanitize_text_field((string) $_GET['bressol_prefill_slot'])
            : '';

        $prefillPid = isset($_GET['bressol_prefill_product_id'])
            ? (int) $_GET['bressol_prefill_product_id']
            : 0;

        $definitionHash = md5($json);
        $basePrice = $product ? (float) $product->get_price() : 0.0;

        echo '<div class="bressol-pack-form" data-pack-id="' . esc_attr((string) $productId) . '" data-pack-hash="' . esc_attr($definitionHash) . '" data-pack-base-price="' . esc_attr((string) $basePrice) . '" data-prefill-slot="' . esc_attr($prefillSlot) . '" data-prefill-product-id="' . esc_attr((string) $prefillPid) . '">';
        echo '<div class="bressol-pack-form__header">';
        echo '<h3 class="bressol-pack-form__title">' . esc_html__('Personaliza tu pack', 'bressol-core') . '</h3>';
        echo '<p class="bressol-pack-form__hint">' . esc_html($hintLabel) . '</p>';
        echo '</div>';
        echo '<aside class="bressol-pack-summary" aria-live="polite">';
        echo '<div class="bressol-pack-summary__header">';
        echo '<h4 class="bressol-pack-summary__title">' . esc_html__('Pack summary', 'bressol-core') . '</h4>';
        echo '<button type="button" class="bressol-pack-summary__toggle" aria-expanded="true">' . esc_html__('Toon details', 'bressol-core') . '</button>';
        echo '</div>';
        echo '<div class="bressol-pack-summary__body">';
        echo '<div class="bressol-pack-summary__list"></div>';
        echo '<div class="bressol-pack-summary__prices">';
        echo '<div><span>' . esc_html__('Basis', 'bressol-core') . '</span><strong class="bressol-pack-summary__base"></strong></div>';
        echo '<div><span>' . esc_html__('Recargo', 'bressol-core') . '</span><strong class="bressol-pack-summary__surcharge"></strong></div>';
        echo '<div><span>' . esc_html__('Totaal (indicatief)', 'bressol-core') . '</span><strong class="bressol-pack-summary__total"></strong></div>';
        echo '</div>';
        echo '<button type="button" class="bressol-pack-summary__reset">' . esc_html__('Reset alles', 'bressol-core') . '</button>';
        echo '</div>';
        echo '</aside>';

        $groups = $this->build_groups($definition['slots']);
        foreach ($groups as $group) {
            $groupKey = $group['group_key'];
            $groupLabel = $group['label'];
            $maxTotal = $group['max_total'];
            $minTotal = $group['min_total'];
            $required = $group['required'];
            $grouped = $group['grouped'];
            $sinkKey = $group['sink_key'];
            $slotsJson = wp_json_encode($group['slots_meta']);
            $slotKeysJson = wp_json_encode($group['slot_keys']);

            $slotHint = $maxTotal <= 1
                ? esc_html__('Kies 1 item.', 'bressol-core')
                : sprintf(
                    /* translators: %d: max items */
                    esc_html__('Kies %d items.', 'bressol-core'),
                    $maxTotal
                );
            if ($minTotal > 0 && $maxTotal > 1) {
                $slotHint .= ' ' . sprintf(
                    /* translators: %d: min items */
                    esc_html__('Minimum %d.', 'bressol-core'),
                    $minTotal
                );
            }

            echo '<div class="bressol-pack-group" data-group-key="' . esc_attr($groupKey) . '" data-group-max="' . esc_attr((string) $maxTotal) . '" data-group-min="' . esc_attr((string) $minTotal) . '" data-group-required="' . esc_attr($required ? '1' : '0') . '" data-slot-keys="' . esc_attr((string) $slotKeysJson) . '">';
            echo '<div class="bressol-pack-slot__header">';
            echo '<h3 class="bressol-pack-slot__title">' . esc_html($groupLabel) . '</h3>';
            echo '<p class="bressol-pack-slot__hint">' . esc_html($slotHint) . '</p>';
            echo '<p class="bressol-pack-slot__count" aria-live="polite">' . esc_html__('Gekozen: 0 / ', 'bressol-core') . esc_html((string) $maxTotal) . '</p>';
            echo '<p class="bressol-pack-slot__remaining" aria-live="polite">' . esc_html__('Resterend: ', 'bressol-core') . esc_html((string) $maxTotal) . '</p>';
            echo '<button type="button" class="bressol-pack-group__reset">' . esc_html__('Reset', 'bressol-core') . '</button>';
            echo '</div>';
            echo '<p class="bressol-pack-group__message" aria-live="polite"></p>';
            if ($grouped) {
                echo '<div class="bressol-pack-group__hidden" data-slots="' . esc_attr((string) $slotsJson) . '" data-sink-key="' . esc_attr($sinkKey) . '">';
                foreach ($group['slot_keys'] as $slotKey) {
                    echo '<input type="hidden" name="bressol_pack[' . esc_attr($slotKey) . ']" value="" />';
                }
                echo '</div>';
            }
            echo '<div class="bressol-pack-carousel" role="list">';

            $useRadio = !$grouped && $group['single_slot_max'] <= 1;
            $selectedValue = '';
            if ($useRadio && !empty($group['options'])) {
                $firstOpt = $group['options'][0];
                $selectedValue = (int) $firstOpt['product_id'] > 0
                    ? ((int) $firstOpt['product_id']) . '|' . ((float) $firstOpt['surcharge'])
                    : '';
                if ($prefillPid > 0 && $prefillSlot === $sinkKey) {
                    foreach ($group['options'] as $opt) {
                        if ((int) $opt['product_id'] === $prefillPid) {
                            $selectedValue = $prefillPid . '|' . ((float) $opt['surcharge']);
                            break;
                        }
                    }
                }
            }

            foreach ($group['options'] as $opt) {
                $pid = (int) $opt['product_id'];
                $p = $opt['product'];
                if (!$p) {
                    continue;
                }

                $surcharge = (float) $opt['surcharge'];
                $optLabel = (string) $opt['label'];
                $priceHtml = $p->get_price_html();
                $surchargeLabel = $surcharge > 0 ? strip_tags(wc_price($surcharge)) : '';
                $imageUrl = $p->get_image_id()
                    ? (string) wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail')
                    : '';
                $defaultQty = 0;

                if ($prefillPid > 0 && $prefillSlot !== '') {
                    if (in_array($prefillSlot, $group['slot_keys'], true) && $prefillPid === $pid) {
                        $defaultQty = 1;
                    }
                }

                echo '<div class="bressol-pack-card" role="listitem" data-product-id="' . esc_attr((string) $pid) . '" data-product-title="' . esc_attr($optLabel) . '" data-product-image="' . esc_url($imageUrl) . '" data-surcharge="' . esc_attr((string) $surcharge) . '">';

                if ($useRadio) {
                    $value = $pid . '|' . $surcharge;
                    $cardId = 'bressol-pack-' . sanitize_key($sinkKey) . '-' . $pid;
                    $checked = checked($selectedValue, $value, false);
                    echo '<input class="bressol-pack-input" type="radio" id="' . esc_attr($cardId) . '" name="bressol_pack[' . esc_attr($sinkKey) . ']" value="' . esc_attr($value) . '" data-group-key="' . esc_attr($groupKey) . '" data-product-id="' . esc_attr((string) $pid) . '" data-product-title="' . esc_attr($optLabel) . '" data-product-image="' . esc_url($imageUrl) . '" data-surcharge="' . esc_attr((string) $surcharge) . '" ' . $checked . '>';
                    echo '<label class="bressol-pack-card__body" for="' . esc_attr($cardId) . '">';
                    echo '<span class="bressol-pack-card__media">' . wp_kses_post($p->get_image('woocommerce_thumbnail')) . '</span>';
                    echo '<span class="bressol-pack-card__title">' . esc_html($optLabel) . '</span>';
                    if ($priceHtml !== '') {
                        echo '<span class="bressol-pack-card__price">' . wp_kses_post($priceHtml) . '</span>';
                    }
                    if ($surchargeLabel !== '') {
                        echo '<span class="bressol-pack-card__surcharge">+ ' . esc_html($surchargeLabel) . '</span>';
                    }
                    echo '</label>';
                } else {
                    echo '<div class="bressol-pack-card__body">';
                    echo '<span class="bressol-pack-card__media">' . wp_kses_post($p->get_image('woocommerce_thumbnail')) . '</span>';
                    echo '<span class="bressol-pack-card__title">' . esc_html($optLabel) . '</span>';
                    if ($priceHtml !== '') {
                        echo '<span class="bressol-pack-card__price">' . wp_kses_post($priceHtml) . '</span>';
                    }
                    if ($surchargeLabel !== '') {
                        echo '<span class="bressol-pack-card__surcharge">+ ' . esc_html($surchargeLabel) . '</span>';
                    }
                    echo '<span class="bressol-pack-card__qty-badge" aria-hidden="true"></span>';
                    echo '<div class="bressol-pack-qty" data-group-key="' . esc_attr($groupKey) . '" data-product-id="' . esc_attr((string) $pid) . '">';
                    echo '<button type="button" class="bressol-pack-qty-btn" data-action="decrease" aria-label="' . esc_attr__('Minder', 'bressol-core') . '">-</button>';
                    echo '<input class="bressol-pack-qty-input" type="number" min="0" max="' . esc_attr((string) $maxTotal) . '" step="1" value="' . esc_attr((string) $defaultQty) . '" ';
                    if (!$grouped) {
                        echo 'name="bressol_pack[' . esc_attr($sinkKey) . '][' . esc_attr((string) $pid) . ']" ';
                    }
                    echo 'data-product-id="' . esc_attr((string) $pid) . '" data-product-title="' . esc_attr($optLabel) . '" data-product-image="' . esc_url($imageUrl) . '" data-surcharge="' . esc_attr((string) $surcharge) . '" aria-label="' . esc_attr__('Aantal', 'bressol-core') . '">';
                    echo '<button type="button" class="bressol-pack-qty-btn" data-action="increase" aria-label="' . esc_attr__('Meer', 'bressol-core') . '">+</button>';
                    echo '</div>';
                    echo '</div>';
                }

                echo '<button type="button" class="bressol-card-info-trigger" data-product-id="' . esc_attr((string) $pid) . '" data-context="pack" aria-expanded="false" aria-controls="bressol-card-info-' . esc_attr((string) $pid) . '-' . esc_attr($groupKey) . '" aria-label="' . esc_attr__('Productinfo', 'bressol-core') . '">i</button>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function enqueueAssets(): void
    {
        if (is_admin()) {
            return;
        }
        if (!function_exists('is_product') || !is_product()) {
            return;
        }
        $productId = (int) get_queried_object_id();
        if ($productId <= 0) {
            return;
        }
        $json = (string) get_post_meta($productId, self::META_KEY, true);
        if ($json === '') {
            return;
        }

        $css = plugins_url(
            'assets/css/bressol-pack-ui.css',
            WP_PLUGIN_DIR . '/bressol-core/bressol-core.php'
        );
        $js = plugins_url(
            'assets/js/bressol-pack-ui.js',
            WP_PLUGIN_DIR . '/bressol-core/bressol-core.php'
        );

        wp_enqueue_style('bressol-pack-ui', $css, [], '0.1.0');
        wp_enqueue_script('bressol-pack-ui', $js, [], '0.1.0', true);
    }

    /** @param array<int, array<string, mixed>> $slots
     *  @return array<int, array<string, mixed>>
     */
    private function build_groups(array $slots): array
    {
        $candidates = [];
        $index = 0;
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $key = isset($slot['key']) ? (string) $slot['key'] : '';
            if ($key === '') {
                continue;
            }
            $groupKey = $this->resolve_group_key($slot);
            if ($groupKey === '') {
                $groupKey = $key;
            }

            if (!isset($candidates[$groupKey])) {
                $candidates[$groupKey] = [
                    'group_key' => $groupKey,
                    'label' => (string) ($slot['label'] ?? $key),
                    'slots' => [],
                    'order' => $index++,
                ];
            }
            $candidates[$groupKey]['slots'][] = $slot;
        }

        $groups = [];
        foreach ($candidates as $candidate) {
            $slotList = $candidate['slots'];
            $canGroup = $this->can_group_slots($slotList);

            if (!$canGroup) {
                foreach ($slotList as $slot) {
                    if (!is_array($slot)) {
                        continue;
                    }
                    $key = isset($slot['key']) ? (string) $slot['key'] : '';
                    if ($key === '') {
                        continue;
                    }
                    $groups[] = $this->build_group_from_slots([$slot], $key, $candidate['order']);
                }
                continue;
            }

            $groups[] = $this->build_group_from_slots($slotList, $candidate['group_key'], $candidate['order']);
        }

        usort($groups, static function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });
        foreach ($groups as &$group) {
            $group['grouped'] = count($group['slot_keys']) > 1;
            $group['options'] = array_values($group['options']);
        }
        unset($group);

        return $groups;
    }

    /** @param array<string, mixed> $slot */
    private function resolve_group_key(array $slot): string
    {
        if (!empty($slot['group_key'])) {
            return $this->normalize_group_key((string) $slot['group_key']);
        }
        if (!empty($slot['taxonomy'])) {
            return $this->normalize_group_key((string) $slot['taxonomy']);
        }
        if (!empty($slot['category'])) {
            return $this->normalize_group_key((string) $slot['category']);
        }
        if (!empty($slot['label'])) {
            return $this->normalize_group_key($this->strip_label_suffix((string) $slot['label']));
        }
        return '';
    }

    private function normalize_group_key(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $sanitized = sanitize_key($raw);
        $length = strlen($sanitized);
        if ($sanitized === '' || $length < 2 || $length > 32) {
            return '';
        }
        return $sanitized;
    }

    /** @param array<int, array<string, mixed>> $slots */
    private function can_group_slots(array $slots): bool
    {
        if (count($slots) < 2) {
            return false;
        }

        $firstMin = null;
        $firstRequired = null;
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                return false;
            }
            $max = (int) ($slot['max'] ?? 1);
            if ($max > 1) {
                return false;
            }
            $min = (int) ($slot['min'] ?? 0);
            $required = !empty($slot['required']);
            if ($firstMin === null) {
                $firstMin = $min;
            }
            if ($firstRequired === null) {
                $firstRequired = $required;
            }
            if ($firstMin !== $min || $firstRequired !== $required) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, array<string, mixed>> $slots */
    private function build_group_from_slots(array $slots, string $groupKey, int $order): array
    {
        $group = [
            'group_key' => $groupKey,
            'label' => $groupKey,
            'slot_keys' => [],
            'slots_meta' => [],
            'options' => [],
            'max_total' => 0,
            'min_total' => 0,
            'required' => false,
            'sink_key' => '',
            'order' => $order,
            'single_slot_max' => 1,
        ];

        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $key = isset($slot['key']) ? (string) $slot['key'] : '';
            if ($key === '') {
                continue;
            }
            if ($group['sink_key'] === '') {
                $group['sink_key'] = $key;
            }
            if ($group['label'] === $groupKey) {
                $label = isset($slot['label']) ? (string) $slot['label'] : $key;
                $group['label'] = $this->strip_label_suffix($label);
            }
            $max = (int) ($slot['max'] ?? 1);
            $min = (int) ($slot['min'] ?? 0);
            $group['single_slot_max'] = $max;
            $group['max_total'] += $max;
            $group['min_total'] += $min;
            $group['required'] = $group['required'] || !empty($slot['required']);
            $group['slot_keys'][] = $key;
            $group['slots_meta'][] = ['key' => $key, 'max' => $max];

            foreach (($slot['options'] ?? []) as $opt) {
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
                $surcharge = (float) ($opt['surcharge'] ?? 0.0);
                $label = isset($opt['label']) && $opt['label'] !== '' ? (string) $opt['label'] : $p->get_name();

                if (!isset($group['options'][$pid])) {
                    $group['options'][$pid] = [
                        'product_id' => $pid,
                        'product' => $p,
                        'label' => $label,
                        'surcharge' => $surcharge,
                    ];
                } else {
                    if ($label !== '' && $group['options'][$pid]['label'] === '') {
                        $group['options'][$pid]['label'] = $label;
                    }
                    if ($surcharge > (float) $group['options'][$pid]['surcharge']) {
                        $group['options'][$pid]['surcharge'] = $surcharge;
                    }
                }
            }
        }

        if (count($group['slot_keys']) > 1) {
            $group['max_total'] = count($group['slot_keys']);
        }

        return $group;
    }

    private function strip_label_suffix(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return $label;
        }
        return trim(preg_replace('/[\s-]*\d+$/', '', $label));
    }
}
