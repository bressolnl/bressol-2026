<?php
/**
 * Template Name: Pakketten
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$woo_active = class_exists('WooCommerce') && function_exists('woocommerce_product_loop');
$advies_link = bressol_get_page_link('advies', '/');
$moments_link = bressol_get_page_link('momenten', '/');
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
    <section class="bressol-section bressol-hero bressol-hero--plp">
        <div class="bressol-container bressol-hero__inner">
            <div class="bressol-hero__content">
                <h1 class="bressol-title"><?php esc_html_e('Pakketten met mediterrane signatuur.', 'bressol-theme'); ?></h1>
                <p class="bressol-lead">
                    <?php esc_html_e('Onze pakketten zijn zorgvuldig samengesteld en waar gewenst te verfijnen. Ideaal voor cadeau, borrel of tafel.', 'bressol-theme'); ?>
                </p>
                <div class="bressol-cta-panel">
                    <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                    <a class="bressol-link" href="<?php echo esc_url($moments_link); ?>">
                        <?php esc_html_e('Bekijk momenten', 'bressol-theme'); ?>
                    </a>
                </div>
            </div>
            <div class="bressol-hero__visual" aria-hidden="true"></div>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Kies je stijl', 'bressol-theme'); ?></h2>
            <div class="bressol-grid">
                <article class="bressol-card" id="cadeau">
                    <h3 class="bressol-card__title"><?php esc_html_e('Cadeau', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Een elegante selectie met een duidelijke signatuur.', 'bressol-theme'); ?></p>
                    <a class="bressol-link" href="#cadeau"><?php esc_html_e('Bekijk cadeau-stijl', 'bressol-theme'); ?></a>
                </article>
                <article class="bressol-card" id="borrel">
                    <h3 class="bressol-card__title"><?php esc_html_e('Borrel', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Rustige combinaties voor een ontspannen moment.', 'bressol-theme'); ?></p>
                    <a class="bressol-link" href="#borrel"><?php esc_html_e('Bekijk borrel-stijl', 'bressol-theme'); ?></a>
                </article>
                <article class="bressol-card" id="koken">
                    <h3 class="bressol-card__title"><?php esc_html_e('Koken & tafel', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Selecties die aansluiten op jouw tafel.', 'bressol-theme'); ?></p>
                    <a class="bressol-link" href="#koken"><?php esc_html_e('Bekijk tafel-stijl', 'bressol-theme'); ?></a>
                </article>
            </div>
        </div>
    </section>

    <section class="bressol-section bressol-trust">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Waarom een pakket', 'bressol-theme'); ?></h2>
            <ul class="bressol-trust-bar">
                <li><?php esc_html_e('Curatie per moment', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Smaak in balans', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Zorgvuldig verpakt', 'bressol-theme'); ?></li>
            </ul>
        </div>
    </section>

    <?php if (!$woo_active) : ?>
        <section class="bressol-section">
            <div class="bressol-container">
                <p class="bressol-lead"><?php esc_html_e('Het overzicht is tijdelijk niet beschikbaar. We helpen je graag met advies op maat.', 'bressol-theme'); ?></p>
                <p>
                    <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                </p>
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
                        <p class="bressol-empty"><?php esc_html_e('Er zijn op dit moment geen pakketten gevonden.', 'bressol-theme'); ?></p>
                        <p>
                            <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                                <?php esc_html_e('Vraag advies op maat', 'bressol-theme'); ?>
                            </a>
                        </p>
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

    <section class="bressol-section">
        <div class="bressol-container">
            <div class="bressol-cta-panel">
                <div>
                    <h2 class="bressol-section-title"><?php esc_html_e('Nog twijfels?', 'bressol-theme'); ?></h2>
                    <p class="bressol-lead"><?php esc_html_e('Laat je adviseren en krijg een selectie die past bij jouw moment.', 'bressol-theme'); ?></p>
                </div>
                <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                    <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                </a>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
