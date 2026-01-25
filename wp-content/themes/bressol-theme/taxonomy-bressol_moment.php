<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$advies_link = bressol_get_page_link('advies', '/');
$pakketten_link = bressol_get_page_link('pakketten', '/');
$term_title = single_term_title('', false);
$term_label = trim(wp_strip_all_tags($term_title));
if ($term_label === '') {
    $term_label = __('dit moment', 'bressol-theme');
}
$term_description = term_description();
$term_id = get_queried_object_id();
$intro_html = $term_id ? (string) get_term_meta($term_id, 'bressol_moment_intro', true) : '';
$longform_html = $term_id ? (string) get_term_meta($term_id, 'bressol_moment_longform', true) : '';
$faq_raw = $term_id ? (string) get_term_meta($term_id, 'bressol_moment_faq', true) : '';
$intro_output = $intro_html !== '' ? $intro_html : $term_description;
$intro_fallback = sprintf(
    __('Een verfijnde selectie voor %s, gemaakt om het moment rustig te dragen.', 'bressol-theme'),
    $term_label
);
$longform_fallback = sprintf(
    __('Voor %s vind je combinaties met karakter en zachte balans. We selecteren op herkomst en ambacht, met ruimte voor persoonlijk advies.', 'bressol-theme'),
    $term_label
);
$intro_text = $intro_output !== '' ? $intro_output : $intro_fallback;
$longform_text = $longform_html !== '' ? $longform_html : $longform_fallback;
$woo_active = class_exists('WooCommerce') && function_exists('woocommerce_product_loop');
?>

<?php if ($woo_active) : ?>
    <?php do_action('woocommerce_before_main_content'); ?>
<?php endif; ?>

<?php if (!$woo_active) : ?>
    <main class="bressol-main">
<?php endif; ?>

<section class="bressol-section bressol-hero bressol-hero--plp">
    <div class="bressol-container bressol-hero__inner">
        <div class="bressol-hero__content">
            <h1 class="bressol-title"><?php echo esc_html($term_title); ?></h1>
            <div class="bressol-lead">
                <?php echo wp_kses_post(wpautop($intro_text)); ?>
            </div>
            <div class="bressol-cta-panel">
                <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                    <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                </a>
                <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>">
                    <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
                </a>
            </div>
        </div>
        <div class="bressol-hero__visual" aria-hidden="true"></div>
    </div>
</section>

<section class="bressol-section bressol-trust">
    <div class="bressol-container">
        <h2 class="bressol-section-title"><?php esc_html_e('Waarom dit moment', 'bressol-theme'); ?></h2>
        <ul class="bressol-trust-bar">
            <li><?php esc_html_e('Herkomst met karakter', 'bressol-theme'); ?></li>
            <li><?php esc_html_e('Ambachtelijk gekozen', 'bressol-theme'); ?></li>
            <li><?php esc_html_e('Smaak in balans', 'bressol-theme'); ?></li>
            <li><?php esc_html_e('Zorgvuldig verpakt', 'bressol-theme'); ?></li>
        </ul>
    </div>
</section>

<section class="bressol-section">
    <div class="bressol-container">
        <h2 class="bressol-section-title"><?php esc_html_e('Waarom deze selectie', 'bressol-theme'); ?></h2>
        <div class="bressol-grid">
            <article class="bressol-card">
                <h3 class="bressol-card__title"><?php esc_html_e('Oorsprong & ambacht', 'bressol-theme'); ?></h3>
                <p class="bressol-card__meta"><?php esc_html_e('Selecties met herkomst en een rustige ambachtelijke stijl.', 'bressol-theme'); ?></p>
            </article>
            <article class="bressol-card">
                <h3 class="bressol-card__title"><?php esc_html_e('Kwaliteit & balans', 'bressol-theme'); ?></h3>
                <p class="bressol-card__meta"><?php esc_html_e('Curatie met smaakbalans en een consistente signatuur.', 'bressol-theme'); ?></p>
            </article>
            <article class="bressol-card">
                <h3 class="bressol-card__title"><?php esc_html_e('Service & begeleiding', 'bressol-theme'); ?></h3>
                <p class="bressol-card__meta"><?php esc_html_e('Persoonlijk advies wanneer je extra richting zoekt.', 'bressol-theme'); ?></p>
            </article>
        </div>
    </div>
</section>

<section class="bressol-section">
    <div class="bressol-container">
        <h2 class="bressol-section-title"><?php esc_html_e('Verzameling', 'bressol-theme'); ?></h2>
        <?php if ($woo_active) : ?>
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
                <p class="bressol-empty"><?php esc_html_e('Er zijn momenteel geen producten zichtbaar voor dit moment.', 'bressol-theme'); ?></p>
                <p>
                    <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Vraag advies op maat', 'bressol-theme'); ?>
                    </a>
                </p>
            <?php endif; ?>
        <?php else : ?>
            <p class="bressol-empty"><?php esc_html_e('Het overzicht is tijdelijk niet beschikbaar. We helpen je graag met advies.', 'bressol-theme'); ?></p>
            <p>
                <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                    <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="bressol-section">
    <div class="bressol-container">
        <h2 class="bressol-section-title"><?php esc_html_e('Verhaal', 'bressol-theme'); ?></h2>
        <div class="bressol-prose">
            <?php echo wp_kses_post(wpautop($longform_text)); ?>
        </div>
    </div>
</section>

<section class="bressol-section">
    <div class="bressol-container">
        <h2 class="bressol-section-title"><?php esc_html_e('Klaar om te kiezen?', 'bressol-theme'); ?></h2>
        <div class="bressol-cta-panel">
            <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
            </a>
            <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>">
                <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
            </a>
        </div>
    </div>
</section>

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
            <div class="bressol-prose bressol-prose--faq">
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
        </div>
    </section>
    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Hulp nodig?', 'bressol-theme'); ?></h2>
            <p class="bressol-lead"><?php esc_html_e('Advies op maat helpt je sneller kiezen.', 'bressol-theme'); ?></p>
            <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                <?php esc_html_e('Vraag advies op maat', 'bressol-theme'); ?>
            </a>
        </div>
    </section>
<?php endif; ?>

<?php if ($woo_active) : ?>
    <?php do_action('woocommerce_after_main_content'); ?>
<?php endif; ?>

<?php
if (!$woo_active) :
?>
</main>
<?php
endif;
get_footer();
