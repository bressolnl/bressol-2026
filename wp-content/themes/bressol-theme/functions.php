<?php
/**
 * Bressol Theme functions.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    register_nav_menus([
        'primary' => __('Hoofdnavigatie', 'bressol-theme'),
    ]);
});

add_action('wp_enqueue_scripts', function () {
    $style_path = get_stylesheet_directory() . '/style.css';
    $style_version = is_readable($style_path) ? (string) filemtime($style_path) : '0.1.0';
    wp_enqueue_style('bressol-theme', get_stylesheet_uri(), [], $style_version);
});

add_action('wp_head', function () {
    do_action('bressol_cmp_head');
}, 1);

add_action('wp_head', function () {
    if (!function_exists('is_tax') || !is_tax('bressol_moment')) {
        return;
    }
    if (function_exists('rank_math') || defined('WPSEO_VERSION')) {
        return;
    }

    $term = get_queried_object();
    if (!$term || is_wp_error($term)) {
        return;
    }

    $link = get_term_link($term);
    if (is_wp_error($link)) {
        return;
    }

    echo '<link rel="canonical" href="' . esc_url($link) . '" />';
}, 2);

if (!function_exists('bressol_get_page_link')) {
    function bressol_get_page_link(string $slug, string $fallback = '/'): string
    {
        $path = $slug;
        $page = get_page_by_path($path);
        if ($page) {
            return get_permalink($page);
        }

        $normalized = $fallback !== '' && $fallback[0] !== '/'
            ? '/' . $fallback
            : $fallback;
        return home_url($normalized ?: '/');
    }
}

add_filter('nav_menu_link_attributes', function (array $atts, $item, $args) {
    if (!is_object($args) || !isset($args->theme_location) || $args->theme_location !== 'primary') {
        return $atts;
    }
    $atts['class'] = trim(($atts['class'] ?? '') . ' bressol-nav__link');
    return $atts;
}, 10, 3);

add_filter('nav_menu_css_class', function (array $classes, $item, $args) {
    if (!is_object($args) || !isset($args->theme_location) || $args->theme_location !== 'primary') {
        return $classes;
    }
    $classes[] = 'bressol-nav__item';
    return $classes;
}, 10, 3);

if (!defined('BRESSOL_ALCOHOL_CATEGORY_SLUG')) {
    define('BRESSOL_ALCOHOL_CATEGORY_SLUG', 'drinks');
}

if (!defined('BRESSOL_ALCOHOL_BADGE')) {
    define('BRESSOL_ALCOHOL_BADGE', '18+');
}

function bressol_alcohol_checkbox_label_text(): string
{
    return esc_html__('Ik bevestig dat ik 18 jaar of ouder ben.', 'bressol-theme');
}

function bressol_alcohol_checkout_error_text(): string
{
    return esc_html__('Bevestig dat je 18 jaar of ouder bent om alcohol te kopen.', 'bressol-theme');
}

function bressol_alcohol_is_confirmed($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower((string) $value);
    return in_array($normalized, ['1', 'yes', 'true', 'on'], true);
}

function bressol_get_drinks_term_id(): int
{
    static $drinks_id = null;
    if ($drinks_id !== null) {
        return $drinks_id;
    }
    if (!taxonomy_exists('product_cat')) {
        $drinks_id = 0;
        return $drinks_id;
    }
    $term = get_term_by('slug', BRESSOL_ALCOHOL_CATEGORY_SLUG, 'product_cat');
    $drinks_id = $term && !is_wp_error($term) ? (int) $term->term_id : 0;
    return $drinks_id;
}

function bressol_is_product_alcohol(int $product_id): bool
{
    static $cache = [];
    if (isset($cache[$product_id])) {
        return $cache[$product_id];
    }
    if (!$product_id || !taxonomy_exists('product_cat')) {
        $cache[$product_id] = false;
        return false;
    }
    $drinks_id = bressol_get_drinks_term_id();
    if (!$drinks_id) {
        $cache[$product_id] = false;
        return false;
    }
    $terms = get_the_terms($product_id, 'product_cat');
    if (!$terms || is_wp_error($terms)) {
        $cache[$product_id] = false;
        return false;
    }
    foreach ($terms as $term) {
        if ((int) $term->term_id === $drinks_id) {
            $cache[$product_id] = true;
            return true;
        }
        if (term_is_ancestor_of($drinks_id, $term->term_id, 'product_cat')) {
            $cache[$product_id] = true;
            return true;
        }
    }
    $cache[$product_id] = false;
    return $cache[$product_id];
}

function bressol_cart_contains_alcohol(): bool
{
    static $contains = null;
    if ($contains !== null) {
        return $contains;
    }
    if (!function_exists('WC') || !WC()->cart || !taxonomy_exists('product_cat')) {
        $contains = false;
        return $contains;
    }
    foreach (WC()->cart->get_cart() as $item) {
        $product_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $variation_id = isset($item['variation_id']) ? (int) $item['variation_id'] : 0;
        if ($product_id && bressol_is_product_alcohol($product_id)) {
            $contains = true;
            return $contains;
        }
        if ($variation_id && bressol_is_product_alcohol($variation_id)) {
            $contains = true;
            return $contains;
        }
    }
    $contains = false;
    return $contains;
}

add_action('woocommerce_review_order_before_submit', function () {
    if (!function_exists('WC') || !WC()->checkout()) {
        return;
    }
    if (!bressol_cart_contains_alcohol()) {
        return;
    }
    woocommerce_form_field('bressol_age_confirm', [
        'type' => 'checkbox',
        'label' => bressol_alcohol_checkbox_label_text(),
        'required' => true,
        'class' => ['form-row-wide', 'bressol-age-consent'],
        'id' => 'bressol_age_confirm_ui',
    ], WC()->checkout()->get_value('bressol_age_confirm'));
});

add_filter('woocommerce_checkout_fields', function (array $fields) {
    if (!bressol_cart_contains_alcohol()) {
        return $fields;
    }
    if (!isset($fields['order'])) {
        $fields['order'] = [];
    }
    $fields['order']['bressol_age_confirm'] = [
        'type' => 'checkbox',
        'label' => bressol_alcohol_checkbox_label_text(),
        'required' => true,
        'priority' => 999,
        'class' => ['bressol-hidden-field'],
        'label_class' => ['bressol-hidden-field'],
        'id' => 'bressol_age_confirm_hidden',
    ];
    return $fields;
});

add_action('woocommerce_checkout_process', function () {
    if (!function_exists('WC')) {
        return;
    }
    if (!bressol_cart_contains_alcohol()) {
        return;
    }
    $raw = isset($_POST['bressol_age_confirm']) ? wp_unslash($_POST['bressol_age_confirm']) : null;
    if (!bressol_alcohol_is_confirmed($raw)) {
        wc_add_notice(bressol_alcohol_checkout_error_text(), 'error');
    }
});

add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    if (!function_exists('WC')) {
        return;
    }
    if (!bressol_cart_contains_alcohol()) {
        return;
    }
    if (bressol_alcohol_is_confirmed($data['bressol_age_confirm'] ?? null)) {
        return;
    }
    if (is_wp_error($errors)) {
        $errors->add('bressol_age_confirm', bressol_alcohol_checkout_error_text());
    }
}, 10, 2);