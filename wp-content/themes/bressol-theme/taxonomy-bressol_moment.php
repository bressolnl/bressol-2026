<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header('shop');

do_action('woocommerce_before_main_content');

$term = get_queried_object();
$term_id = $term && !is_wp_error($term) && isset($term->term_id) ? (int) $term->term_id : 0;
$term_name = $term && !is_wp_error($term) && isset($term->name) ? $term->name : esc_html__('Moment', 'bressol-theme');
$intro = $term_id ? (string) term_description($term_id, 'bressol_moment') : '';
$intro_is_html = (bool) $intro;
$intro_fallback = esc_html__('Een zorgvuldig samengesteld aanbod dat past bij de sfeer en het ritme van dit moment.', 'bressol-theme');
$pakketten_link = home_url('/pakketten/');
$drinks_term = get_term_by('slug', 'drinks', 'product_cat');
$drinks_link = $drinks_term && !is_wp_error($drinks_term) ? get_term_link($drinks_term) : '';
$advies_link = home_url('/advies/');
?>

<main class="bressol-main">
    <div class="bressol-container">
        <header class="bressol-hero bressol-section">
            <h1 class="bressol-hero__title"><?php echo esc_html($term_name); ?></h1>
            <?php if ($intro_is_html) : ?>
                <div class="bressol-lead"><?php echo wp_kses_post(wpautop($intro)); ?></div>
            <?php else : ?>
                <p class="bressol-lead"><?php echo esc_html($intro_fallback); ?></p>
            <?php endif; ?>
            <div class="bressol-hero__actions">
                <a class="bressol-cta" href="<?php echo esc_url($advies_link); ?>">
                    <?php esc_html_e('Start met advies op maat', 'bressol-theme'); ?>
                </a>
            </div>
        </header>

        <section class="bressol-section">
            <?php if (have_posts()) : ?>
                <?php do_action('woocommerce_before_shop_loop'); ?>
                <?php woocommerce_product_loop_start(); ?>
                <?php while (have_posts()) : ?>
                    <?php the_post(); ?>
                    <?php wc_get_template_part('content', 'product'); ?>
                <?php endwhile; ?>
                <?php woocommerce_product_loop_end(); ?>
                <?php do_action('woocommerce_after_shop_loop'); ?>
            <?php else : ?>
                <?php do_action('woocommerce_no_products_found'); ?>
            <?php endif; ?>
        </section>

        <section class="bressol-section">
            <h2 class="bressol-section__title"><?php esc_html_e('Past perfect bij', 'bressol-theme'); ?></h2>
            <ul class="bressol-list">
                <li><?php esc_html_e('Aperitief en ontspannen tafelmomenten.', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Kleine vieringen met een verfijnde selectie.', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Cadeaus die persoonlijk en elegant aanvoelen.', 'bressol-theme'); ?></li>
            </ul>
        </section>

        <div class="bressol-section">
            <a class="bressol-cta" href="<?php echo esc_url($advies_link); ?>">
                <?php esc_html_e('Vraag persoonlijk advies', 'bressol-theme'); ?>
            </a>
            <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>">
                <?php esc_html_e('Bekijk onze pakketten', 'bressol-theme'); ?>
            </a>
            <?php if ($drinks_link && !is_wp_error($drinks_link)) : ?>
                <a class="bressol-link" href="<?php echo esc_url($drinks_link); ?>">
                    <?php esc_html_e('Ontdek dranken', 'bressol-theme'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
do_action('woocommerce_after_main_content');
do_action('woocommerce_sidebar');

get_footer('shop');
