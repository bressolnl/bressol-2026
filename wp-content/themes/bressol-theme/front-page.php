<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$home_data = function_exists('bressol_get_home_data') ? bressol_get_home_data() : [];
?>

<main id="main" class="bressol-main bressol-home">
    <?php
    get_template_part('template-parts/home/hero', null, [
        'data' => $home_data['hero_slides'] ?? [],
    ]);
    get_template_part('template-parts/home/trust-bar', null, [
        'data' => $home_data['trust_items'] ?? [],
    ]);
    get_template_part('template-parts/home/shortcuts', null, [
        'data' => $home_data['shortcuts'] ?? [],
    ]);
    get_template_part('template-parts/home/bestsellers', null, [
        'data' => $home_data['bestsellers'] ?? [],
    ]);
    get_template_part('template-parts/home/moments', null, [
        'data' => $home_data['moments'] ?? [],
    ]);
    get_template_part('template-parts/home/editorials', null, [
        'data' => $home_data['editorials'] ?? [],
    ]);
    get_template_part('template-parts/home/gifts', null, [
        'data' => $home_data['gifts'] ?? [],
    ]);
    get_template_part('template-parts/home/origin-cards', null, [
        'data' => $home_data['origin_cards'] ?? [],
    ]);
    get_template_part('template-parts/home/newsletter', null, [
        'data' => $home_data['newsletter'] ?? [],
    ]);
    ?>
</main>

<?php
get_footer();
