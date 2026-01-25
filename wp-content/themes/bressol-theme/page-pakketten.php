<?php
/**
 * Template Name: Pakketten
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$woo_active = class_exists('WooCommerce') && function_exists('woocommerce_product_loop');
$tier = isset($_GET['tier']) ? sanitize_title((string) $_GET['tier']) : '';
$occasion = isset($_GET['occasion']) ? sanitize_title((string) $_GET['occasion']) : '';
$theme = isset($_GET['theme']) ? sanitize_title((string) $_GET['theme']) : '';
$focus = isset($_GET['focus']) ? sanitize_title((string) $_GET['focus']) : '';

$meta_query = [
    ['key' => '_bressol_pack_definition', 'compare' => 'EXISTS'],
];

if ($tier !== '') {
    $meta_query[] = ['key' => '_bressol_pack_tier', 'value' => $tier, 'compare' => '='];
}
if ($occasion !== '') {
    $meta_query[] = ['key' => '_bressol_pack_occasion', 'value' => $occasion, 'compare' => '='];
}
if ($theme !== '') {
    // REGEXP on postmeta can be expensive; TODO: normalize to taxonomy or table.
    $meta_query[] = ['key' => '_bressol_pack_themes', 'value' => '(^|,)\\s*' . preg_quote($theme, '/') . '\\s*(,|$)', 'compare' => 'REGEXP'];
}
if ($focus !== '') {
    // REGEXP on postmeta can be expensive; TODO: normalize to taxonomy or table.
    $meta_query[] = ['key' => '_bressol_pack_focus', 'value' => '(^|,)\\s*' . preg_quote($focus, '/') . '\\s*(,|$)', 'compare' => 'REGEXP'];
}
?>

<main class="bressol-main">
    <section class="bressol-section">
        <div class="bressol-container">
            <h1 class="bressol-title"><?php esc_html_e('Pakketten', 'bressol-theme'); ?></h1>
            <p class="bressol-lead">
                <?php esc_html_e('Kies een samengesteld pakket en personaliseer waar gewenst.', 'bressol-theme'); ?>
            </p>
            <p class="bressol-lead">
                <?php esc_html_e('Filters verfijnen je selectie op occasion, thema of focus.', 'bressol-theme'); ?>
            </p>
        </div>
    </section>

    <?php if (!$woo_active) : ?>
        <section class="bressol-section">
            <div class="bressol-container">
                <p class="bressol-lead"><?php esc_html_e('Binnenkort beschikbaar.', 'bressol-theme'); ?></p>
            </div>
        </section>
    <?php else : ?>
        <?php
        $query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 24,
            'meta_query' => $meta_query,
        ]);
        global $wp_query;
        $old_query = $wp_query;
        $wp_query = $query;
        try {
            ?>

            <?php if ($query->have_posts()) : ?>
                <?php do_action('woocommerce_before_shop_loop'); ?>
                <?php woocommerce_product_loop_start(); ?>

                <?php while ($query->have_posts()) : ?>
                    <?php $query->the_post(); ?>
                    <?php do_action('woocommerce_shop_loop'); ?>
                    <?php wc_get_template_part('content', 'product'); ?>
                <?php endwhile; ?>

                <?php woocommerce_product_loop_end(); ?>
                <?php do_action('woocommerce_after_shop_loop'); ?>
            <?php else : ?>
                <section class="bressol-section">
                    <div class="bressol-container">
                        <p class="bressol-empty"><?php esc_html_e('Er zijn geen pakketten gevonden.', 'bressol-theme'); ?></p>
                    </div>
                </section>
            <?php endif; ?>
            <?php
        } finally {
            wp_reset_postdata();
            $wp_query = $old_query;
        }
        ?>

        <section class="bressol-section">
            <div class="bressol-container">
                <p class="bressol-lead"><?php esc_html_e('Ontdek ook onze categorieën:', 'bressol-theme'); ?></p>
                <p>
                    <?php foreach (['borrel', 'drinks', 'smaakmakers'] as $slug) : ?>
                        <?php
                        $term = get_term_by('slug', $slug, 'product_cat');
                        $term_link = $term && !is_wp_error($term) ? get_term_link($term) : '';
                        ?>
                        <?php if ($term_link) : ?>
                            <a class="bressol-link" href="<?php echo esc_url($term_link); ?>">
                                <?php echo esc_html($term->name); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </p>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php
get_footer();
