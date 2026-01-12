<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

echo '<main style="padding:24px;">';

if (have_posts()) {
    while (have_posts()) {
        the_post();
        the_content();
    }
} else {
    echo '<h1>Bressol Theme</h1>';
    echo '<p>No hay contenido para mostrar.</p>';
}

echo '</main>';

get_footer();