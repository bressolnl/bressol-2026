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
});

add_action('wp_enqueue_scripts', function () {
    // Cargaremos CSS/JS real más adelante. Hoy lo dejamos intencionalmente vacío.
});