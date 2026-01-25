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
    wp_enqueue_style('bressol-theme', get_stylesheet_uri(), [], '0.1.0');
    wp_enqueue_style(
        'bressol-fonts',
        'https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Montserrat:wght@400;600&display=swap',
        [],
        null
    );
    wp_enqueue_script(
        'bressol-theme',
        get_template_directory_uri() . '/assets/js/theme.js',
        [],
        '0.1.0',
        true
    );
});

add_filter('wp_resource_hints', function (array $urls, string $relation_type) {
    if ($relation_type !== 'preconnect') {
        return $urls;
    }
    $urls[] = 'https://fonts.googleapis.com';
    $urls[] = [
        'href' => 'https://fonts.gstatic.com',
        'crossorigin' => 'anonymous',
    ];
    return $urls;
}, 10, 2);

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

add_action('pre_get_posts', function (WP_Query $query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->is_tax('bressol_moment')) {
        $query->set('post_type', 'product');
        $query->set('posts_per_page', 12);
    }
});

if (!defined('BRESSOL_ALCOHOL_CATEGORY_SLUG')) {
    define('BRESSOL_ALCOHOL_CATEGORY_SLUG', 'drinks');
}

if (!defined('BRESSOL_ALCOHOL_BADGE')) {
    define('BRESSOL_ALCOHOL_BADGE', '18+');
}

function bressol_alcohol_disclaimer_text(): string
{
    return esc_html__('Alcohol wordt enkel verkocht aan 18+. Drink met mate.', 'bressol-theme');
}

function bressol_alcohol_checkout_prompt_text(): string
{
    return esc_html__('Bevestig dat je 18 jaar of ouder bent en verantwoord consumeert.', 'bressol-theme');
}

function bressol_alcohol_checkbox_label_text(): string
{
    return esc_html__('Ik bevestig dat ik 18 jaar of ouder ben.', 'bressol-theme');
}

function bressol_alcohol_checkout_error_text(): string
{
    return esc_html__('Bevestig dat je 18 jaar of ouder bent om alcohol te kopen.', 'bressol-theme');
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

function bressol_is_drinks_archive(): bool
{
    static $is_drinks_archive = null;
    if ($is_drinks_archive !== null) {
        return $is_drinks_archive;
    }
    if (!is_tax('product_cat')) {
        $is_drinks_archive = false;
        return $is_drinks_archive;
    }
    $drinks_id = bressol_get_drinks_term_id();
    if (!$drinks_id) {
        $is_drinks_archive = false;
        return $is_drinks_archive;
    }
    $term = get_queried_object();
    if (!$term || is_wp_error($term) || !isset($term->term_id)) {
        $is_drinks_archive = false;
        return $is_drinks_archive;
    }
    $term_id = (int) $term->term_id;
    if ($term_id === $drinks_id) {
        $is_drinks_archive = true;
        return $is_drinks_archive;
    }
    $is_drinks_archive = term_is_ancestor_of($drinks_id, $term_id, 'product_cat');
    return $is_drinks_archive;
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

function bressol_order_contains_alcohol($order): bool
{
    if (!$order || !method_exists($order, 'get_items')) {
        return false;
    }
    foreach ($order->get_items() as $item) {
        if (!is_object($item)) {
            continue;
        }
        $product_id = method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0;
        $variation_id = method_exists($item, 'get_variation_id') ? (int) $item->get_variation_id() : 0;
        if ($product_id && bressol_is_product_alcohol($product_id)) {
            return true;
        }
        if ($variation_id && bressol_is_product_alcohol($variation_id)) {
            return true;
        }
    }
    return false;
}

function bressol_normalize_checkbox_value($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower((string) $value);
    return in_array($normalized, ['1', 'true', 'yes', 'on', 'checked'], true);
}

function bressol_checkout_data_contains_alcohol(array $data): bool
{
    $line_items = $data['line_items'] ?? null;
    if (!is_array($line_items)) {
        return false;
    }
    foreach ($line_items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $product_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $variation_id = isset($item['variation_id']) ? (int) $item['variation_id'] : 0;
        if ($product_id && bressol_is_product_alcohol($product_id)) {
            return true;
        }
        if ($variation_id && bressol_is_product_alcohol($variation_id)) {
            return true;
        }
    }
    return false;
}

function bressol_checkout_contains_alcohol(array $data = [], $order = null): bool
{
    if ($order && bressol_order_contains_alcohol($order)) {
        return true;
    }
    if (bressol_cart_contains_alcohol()) {
        return true;
    }
    return $data ? bressol_checkout_data_contains_alcohol($data) : false;
}

function bressol_get_age_confirm_value(array $data = []): bool
{
    if (isset($data['bressol_age_confirm'])) {
        return bressol_normalize_checkbox_value($data['bressol_age_confirm']);
    }
    $value = null;
    if (function_exists('WC') && WC()->checkout()) {
        $value = WC()->checkout()->get_value('bressol_age_confirm');
    }
    if ($value === null && isset($_POST['bressol_age_confirm'])) {
        $value = wp_unslash($_POST['bressol_age_confirm']);
    }
    return bressol_normalize_checkbox_value($value);
}

add_action('woocommerce_review_order_before_submit', function () {
    if (!bressol_checkout_contains_alcohol()) {
        return;
    }
    echo '<div class="bressol-checkout-disclaimer">';
    echo '<p><span class="bressol-badge">' . esc_html(BRESSOL_ALCOHOL_BADGE) . '</span>' . bressol_alcohol_checkout_prompt_text() . '</p>';
    if (function_exists('woocommerce_form_field') && function_exists('WC')) {
        woocommerce_form_field('bressol_age_confirm', [
            'type' => 'checkbox',
            'label' => bressol_alcohol_checkbox_label_text(),
            'required' => true,
            'class' => ['form-row-wide', 'bressol-age-consent'],
            'input_class' => ['bressol-age-consent__input'],
        ], WC()->checkout()->get_value('bressol_age_confirm'));
    }
    echo '</div>';
});

add_action('woocommerce_checkout_process', function () {
    if (!bressol_checkout_contains_alcohol()) {
        return;
    }
    // Express checkout flows that bypass checkout_process need separate validation.
    if (!bressol_get_age_confirm_value()) {
        wc_add_notice(
            bressol_alcohol_checkout_error_text(),
            'error'
        );
    }
});

add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    if (!bressol_checkout_contains_alcohol((array) $data)) {
        return;
    }
    if (bressol_get_age_confirm_value((array) $data)) {
        return;
    }
    if (is_wp_error($errors)) {
        $errors->add('bressol_age_confirm', bressol_alcohol_checkout_error_text());
    }
}, 10, 2);

add_filter('woocommerce_store_api_checkout_validation', function ($errors, $request) {
    if (!is_object($errors) || !method_exists($errors, 'add')) {
        return $errors;
    }
    $data = [];
    if (is_object($request) && method_exists($request, 'get_params')) {
        $data = (array) $request->get_params();
    }
    if (!bressol_checkout_contains_alcohol($data)) {
        return $errors;
    }
    if (bressol_get_age_confirm_value($data)) {
        return $errors;
    }
    $errors->add('bressol_age_confirm', bressol_alcohol_checkout_error_text());
    return $errors;
}, 10, 2);