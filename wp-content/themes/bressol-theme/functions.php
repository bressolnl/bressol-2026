<?php
/**
 * Bressol Theme functions.
 */

if (!defined('ABSPATH')) {
    exit;
}

$home_data_path = get_stylesheet_directory() . '/inc/home-data.php';
if (is_readable($home_data_path)) {
    require_once $home_data_path;
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
    $fonts_path = get_stylesheet_directory() . '/assets/fonts/fonts.css';
    if (is_readable($fonts_path)) {
        wp_enqueue_style(
            'bressol-fonts',
            get_stylesheet_directory_uri() . '/assets/fonts/fonts.css',
            [],
            (string) filemtime($fonts_path)
        );
    }
    $style_path = get_stylesheet_directory() . '/style.css';
    $style_version = is_readable($style_path) ? (string) filemtime($style_path) : '0.1.0';
    wp_enqueue_style('bressol-theme', get_stylesheet_uri(), [], $style_version);
});

add_action('wp', function () {
    if (!function_exists('is_tax') || !is_tax('bressol_moment')) {
        return;
    }
    remove_action('wp_head', 'rel_canonical');
}, 1);

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

if (!function_exists('bressol_schema_is_seo_plugin_active')) {
    function bressol_schema_is_seo_plugin_active(): bool
    {
        return defined('RANK_MATH_VERSION')
            || class_exists('RankMath\\Helper')
            || defined('WPSEO_VERSION')
            || class_exists('WPSEO_Frontend');
    }
}

if (!function_exists('bressol_schema_normalize_text')) {
    function bressol_schema_normalize_text(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text));
        return $text ?? '';
    }
}

if (!function_exists('bressol_schema_get_faq_items_from_context')) {
    /** @return array<int, array{question:string, answer:string}> */
    function bressol_schema_get_faq_items_from_context(): array
    {
        if (!function_exists('is_tax') || (!is_tax('product_cat') && !is_tax('bressol_moment'))) {
            return [];
        }

        $term_id = get_queried_object_id();
        if (!$term_id) {
            return [];
        }

        $meta_key = is_tax('product_cat') ? 'bressol_cat_faq' : 'bressol_moment_faq';
        $faq_raw = (string) get_term_meta($term_id, $meta_key, true);
        if ($faq_raw === '') {
            return [];
        }

        $decoded = json_decode($faq_raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $allowed_tags = [
            'br' => [],
            'p' => [],
            'strong' => [],
            'em' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
        ];

        $items = [];
        $seen = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question_raw = (string) ($item['q'] ?? '');
            $answer_raw = (string) ($item['a'] ?? '');

            $question = bressol_schema_normalize_text(wp_strip_all_tags($question_raw));
            $answer = bressol_schema_normalize_text(wp_kses($answer_raw, $allowed_tags));

            if ($question === '' || $answer === '') {
                continue;
            }

            $key = mb_strtolower($question);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $items[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $items;
    }
}

if (!function_exists('bressol_schema_should_print_faq')) {
    function bressol_schema_should_print_faq(): bool
    {
        if (is_admin() || bressol_schema_is_seo_plugin_active()) {
            return false;
        }

        $items = bressol_schema_get_faq_items_from_context();
        return !empty($items);
    }
}

if (!function_exists('bressol_schema_should_print_org')) {
    function bressol_schema_should_print_org(): bool
    {
        return !is_admin() && !bressol_schema_is_seo_plugin_active();
    }
}

if (!function_exists('bressol_schema_should_print_website')) {
    function bressol_schema_should_print_website(): bool
    {
        return !is_admin() && !bressol_schema_is_seo_plugin_active();
    }
}

if (!function_exists('bressol_schema_print_jsonld')) {
    /** @param array<string, mixed> $data */
    function bressol_schema_print_jsonld(array $data): void
    {
        $json = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!$json) {
            return;
        }
        echo "\n<script type=\"application/ld+json\">{$json}</script>\n";
    }
}

add_action('wp_head', function () {
    if (bressol_schema_should_print_org()) {
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Bressol',
            'url' => home_url('/'),
        ];

        $logo_id = (int) get_theme_mod('custom_logo');
        if ($logo_id) {
            $logo_url = wp_get_attachment_image_url($logo_id, 'full');
            if ($logo_url) {
                $org['logo'] = $logo_url;
            }
        }

        bressol_schema_print_jsonld($org);
    }

    if (bressol_schema_should_print_website()) {
        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'Bressol',
            'url' => home_url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => home_url('/?s={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ];

        bressol_schema_print_jsonld($website);
    }

    if (bressol_schema_should_print_faq()) {
        $items = bressol_schema_get_faq_items_from_context();
        $entities = [];
        foreach ($items as $item) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }

        if ($entities !== []) {
            bressol_schema_print_jsonld([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $entities,
            ]);
        }
    }
}, 30);

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