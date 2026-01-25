<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header('shop');

do_action('woocommerce_before_main_content');
?>

<?php if (function_exists('bressol_is_drinks_archive') && bressol_is_drinks_archive()) : ?>
    <div class="bressol-container bressol-section">
        <div class="bressol-disclaimer">
            <p><span class="bressol-badge"><?php echo esc_html(BRESSOL_ALCOHOL_BADGE); ?></span><?php echo bressol_alcohol_disclaimer_text(); ?></p>
        </div>
    </div>
<?php endif; ?>

<header class="woocommerce-products-header bressol-hero bressol-container">
    <?php if (apply_filters('woocommerce_show_page_title', true)) : ?>
        <h1 class="woocommerce-products-header__title page-title bressol-hero__title">
            <?php woocommerce_page_title(); ?>
        </h1>
    <?php endif; ?>

    <div class="woocommerce-products-header__description bressol-hero__content">
        <?php
        do_action('woocommerce_archive_description');
        ?>
        <p class="bressol-lead">
            <?php esc_html_e('Ontdek onze selectie en kies wat past bij jouw moment.', 'bressol-theme'); ?>
        </p>
    </div>
</header>

<div class="woocommerce-products-container bressol-container bressol-section">
    <?php if (woocommerce_product_loop()) : ?>
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
</div>

<?php
do_action('woocommerce_after_main_content');
do_action('woocommerce_sidebar');

get_footer('shop');
