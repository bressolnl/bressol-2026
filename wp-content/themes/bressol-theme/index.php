<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

echo '<main id="main" class="bressol-main">';

if (have_posts()) {
    while (have_posts()) {
        the_post();
        the_content();
    }
} else {
    $shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
    $shop_link = $shop_page_id ? get_permalink($shop_page_id) : home_url('/');
    $advies_link = bressol_get_page_link('advies', '/');
    echo '<section class="bressol-section">';
    echo '<div class="bressol-container">';
    echo '<h1 class="bressol-title">' . esc_html__('Bressol', 'bressol-theme') . '</h1>';
    echo '<p class="bressol-lead">' . esc_html__('Ontdek onze selectie en ontvang advies dat past bij jouw moment.', 'bressol-theme') . '</p>';
    echo '<div class="bressol-cta-panel">';
    echo '<a class="bressol-button" href="' . esc_url($advies_link) . '">' . esc_html__('Start met advies', 'bressol-theme') . '</a>';
    echo '<a class="bressol-link" href="' . esc_url($shop_link) . '">' . esc_html__('Bekijk de shop', 'bressol-theme') . '</a>';
    echo '</div>';
    echo '</div>';
    echo '</section>';
}

echo '</main>';

get_footer();