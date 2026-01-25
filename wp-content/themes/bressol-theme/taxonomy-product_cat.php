<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$advies_link = bressol_get_page_link('advies', '/');
$term_title = single_term_title('', false);
$term_description = term_description();
$term_id = get_queried_object_id();
$intro_html = $term_id ? (string) get_term_meta($term_id, 'bressol_cat_intro', true) : '';
$longform_html = $term_id ? (string) get_term_meta($term_id, 'bressol_cat_longform', true) : '';
$faq_raw = $term_id ? (string) get_term_meta($term_id, 'bressol_cat_faq', true) : '';
$intro_output = $intro_html !== '' ? $intro_html : $term_description;
$woo_active = class_exists('WooCommerce') && function_exists('woocommerce_product_loop');
?>

<?php if ($woo_active) : ?>
    <?php do_action('woocommerce_before_main_content'); ?>
<?php endif; ?>

<section class="bressol-section">
    <div class="bressol-container">
        <h1 class="bressol-title"><?php echo esc_html($term_title); ?></h1>
        <?php if ($intro_output) : ?>
            <div class="bressol-lead">
                <?php echo wp_kses_post(wpautop($intro_output)); ?>
            </div>
        <?php endif; ?>
        <p>
            <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                <?php esc_html_e('Liever advies op maat?', 'bressol-theme'); ?>
            </a>
        </p>
        <?php if (!$woo_active) : ?>
            <?php get_footer(); ?>
            <?php return; ?>
        <?php endif; ?>
    </div>
</section>

<?php if (woocommerce_product_loop()) : ?>
    <?php do_action('woocommerce_before_shop_loop'); ?>
    <?php woocommerce_product_loop_start(); ?>

    <?php while (have_posts()) : ?>
        <?php the_post(); ?>
        <?php do_action('woocommerce_shop_loop'); ?>
        <?php wc_get_template_part('content', 'product'); ?>
    <?php endwhile; ?>

    <?php woocommerce_product_loop_end(); ?>
    <?php do_action('woocommerce_after_shop_loop'); ?>
<?php else : ?>
    <?php do_action('woocommerce_no_products_found'); ?>
<?php endif; ?>

<?php if ($longform_html) : ?>
    <section class="bressol-section">
        <div class="bressol-container">
            <div class="bressol-lead">
                <?php echo wp_kses_post(wpautop($longform_html)); ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php
$faq_items = [];
if ($faq_raw !== '') {
    $decoded = json_decode($faq_raw, true);
    if (is_array($decoded)) {
        $faq_items = $decoded;
    }
}
?>
<?php if (!empty($faq_items)) : ?>
    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Veelgestelde vragen', 'bressol-theme'); ?></h2>
            <div class="bressol-faq">
                <?php foreach ($faq_items as $item) : ?>
                    <?php $q = (string) ($item['q'] ?? ''); ?>
                    <?php $a = (string) ($item['a'] ?? ''); ?>
                    <?php if ($q === '' && $a === '') continue; ?>
                    <article class="bressol-faq__item">
                        <?php if ($q !== '') : ?>
                            <h3 class="bressol-faq__q"><?php echo esc_html($q); ?></h3>
                        <?php endif; ?>
                        <?php if ($a !== '') : ?>
                            <div class="bressol-faq__a"><?php echo wp_kses_post(wpautop($a)); ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($woo_active) : ?>
    <?php do_action('woocommerce_after_main_content'); ?>
<?php endif; ?>

<?php
get_footer();
