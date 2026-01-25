<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header('shop');

do_action('woocommerce_before_main_content');
?>

<div class="woocommerce-product-container bressol-container bressol-section">
    <?php while (have_posts()) : ?>
        <?php the_post(); ?>

        <?php if (function_exists('bressol_is_product_alcohol') && bressol_is_product_alcohol(get_the_ID())) : ?>
            <div class="bressol-disclaimer bressol-section">
                <p><span class="bressol-badge"><?php echo esc_html(BRESSOL_ALCOHOL_BADGE); ?></span><?php echo bressol_alcohol_disclaimer_text(); ?></p>
            </div>
        <?php endif; ?>

        <?php do_action('woocommerce_before_single_product'); ?>

        <?php wc_get_template_part('content', 'single-product'); ?>

        <?php do_action('woocommerce_after_single_product'); ?>
    <?php endwhile; ?>
</div>

<?php
do_action('woocommerce_after_main_content');
do_action('woocommerce_sidebar');

get_footer('shop');
