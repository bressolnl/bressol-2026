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
$shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
$shop_link = $shop_page_id ? get_permalink($shop_page_id) : home_url('/');
$page_id = (int) get_queried_object_id();
$hero_image = $page_id ? get_the_post_thumbnail_url($page_id, 'full') : '';
$hero_style = $hero_image ? ' style="background-image:url(' . esc_url($hero_image) . ');"' : '';
$page = $page_id ? get_post($page_id) : null;
$page_content = $page ? (string) $page->post_content : '';
$show_faq = $page && has_shortcode($page_content, 'bressol_faq');
$filter_base = $page_id ? get_permalink($page_id) : home_url('/');

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
    $meta_query[] = ['key' => '_bressol_pack_themes', 'value' => '(^|,)\\s*' . preg_quote($theme, '/') . '\\s*(,|$)', 'compare' => 'REGEXP'];
}
if ($focus !== '') {
    $meta_query[] = ['key' => '_bressol_pack_focus', 'value' => '(^|,)\\s*' . preg_quote($focus, '/') . '\\s*(,|$)', 'compare' => 'REGEXP'];
}

$moments_link = bressol_get_page_link('momenten', '');
$filter_map = [
    'tier' => [
        'budget' => 'Budget',
        'standard' => 'Standard',
        'premium' => 'Premium',
    ],
    'occasion' => [
        'cadeau' => 'Cadeau',
    ],
    'theme' => [
        'borrel' => 'Borrel',
        'koken' => 'Koken',
    ],
    'focus' => [
        'alcoholvrij' => 'Alcoholvrij',
    ],
];

$current_args = array_filter([
    'tier' => $tier,
    'occasion' => $occasion,
    'theme' => $theme,
    'focus' => $focus,
]);

$active_filters = [];
foreach ($current_args as $key => $value) {
    $label = $filter_map[$key][$value] ?? ucfirst(str_replace('-', ' ', $value));
    $active_filters[] = ucfirst($key) . ': ' . $label;
}
$has_filters = !empty($active_filters);

$query = null;
$personalizable_link = '';
$found_posts = 0;
if ($woo_active) {
    $query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 9,
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'meta_query' => $meta_query,
    ]);
    $found_posts = (int) $query->found_posts;
}
?>

<main class="bressol-main">
    <section class="bressol-section bressol-hero bressol-hero--plp">
        <div class="bressol-container bressol-hero__inner">
            <div class="bressol-hero__content">
                <h1 class="bressol-title"><?php esc_html_e('Pakketten met Valenciaanse stijl.', 'bressol-theme'); ?></h1>
                <p class="bressol-lead">
                    <?php esc_html_e('Sobere selecties met mediterrane balans. Voor borrel, cadeau of tafel — met ruimte om te verfijnen.', 'bressol-theme'); ?>
                </p>
                <p class="bressol-hero__note">
                    <?php esc_html_e('Liever hulp bij kiezen?', 'bressol-theme'); ?>
                    <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                    <?php esc_html_e('of verken de', 'bressol-theme'); ?>
                    <a class="bressol-link" href="<?php echo esc_url($shop_link); ?>">
                        <?php esc_html_e('shop', 'bressol-theme'); ?>
                    </a>
                    <?php if ($moments_link !== '') : ?>
                        <?php esc_html_e('en', 'bressol-theme'); ?>
                        <a class="bressol-link" href="<?php echo esc_url($moments_link); ?>">
                            <?php esc_html_e('momenten', 'bressol-theme'); ?>
                        </a>
                    <?php endif; ?>
                    .
                </p>
                <div class="bressol-cta-panel">
                    <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                    <a class="bressol-button bressol-button--ghost" href="<?php echo esc_url($shop_link); ?>">
                        <?php esc_html_e('Bekijk shop', 'bressol-theme'); ?>
                    </a>
                </div>
            </div>
            <div class="bressol-hero__visual" aria-hidden="true"<?php echo $hero_style; ?>></div>
        </div>
    </section>

    <section class="bressol-section bressol-trust">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Waarom een pakket', 'bressol-theme'); ?></h2>
            <ul class="bressol-trust-bar">
                <li><?php esc_html_e('Samenstelling met rust', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Valenciaanse herkomst', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Cadeau-waardig verpakt', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Eerlijk en helder geprijsd', 'bressol-theme'); ?></li>
            </ul>
            <p class="bressol-lead"><?php esc_html_e('Pakketten die richting geven zonder ruis.', 'bressol-theme'); ?></p>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Kies je stijl', 'bressol-theme'); ?></h2>
            <div class="bressol-chips">
                <?php
                $chip_args = $current_args;
                $chip_args['theme'] = 'borrel';
                ?>
                <a class="bressol-chip<?php echo $theme === 'borrel' ? ' bressol-chip--active' : ''; ?>" href="<?php echo esc_url(add_query_arg($chip_args, $filter_base)); ?>">
                    <?php esc_html_e('Borrel', 'bressol-theme'); ?>
                </a>
                <?php
                $chip_args = $current_args;
                $chip_args['theme'] = 'koken';
                ?>
                <a class="bressol-chip<?php echo $theme === 'koken' ? ' bressol-chip--active' : ''; ?>" href="<?php echo esc_url(add_query_arg($chip_args, $filter_base)); ?>">
                    <?php esc_html_e('Koken', 'bressol-theme'); ?>
                </a>
                <?php
                $chip_args = $current_args;
                $chip_args['occasion'] = 'cadeau';
                ?>
                <a class="bressol-chip<?php echo $occasion === 'cadeau' ? ' bressol-chip--active' : ''; ?>" href="<?php echo esc_url(add_query_arg($chip_args, $filter_base)); ?>">
                    <?php esc_html_e('Cadeau', 'bressol-theme'); ?>
                </a>
                <?php
                $chip_args = $current_args;
                $chip_args['focus'] = 'alcoholvrij';
                ?>
                <a class="bressol-chip<?php echo $focus === 'alcoholvrij' ? ' bressol-chip--active' : ''; ?>" href="<?php echo esc_url(add_query_arg($chip_args, $filter_base)); ?>">
                    <?php esc_html_e('Alcoholvrij', 'bressol-theme'); ?>
                </a>
                <?php if ($has_filters) : ?>
                    <a class="bressol-chip bressol-chip--reset" href="<?php echo esc_url($filter_base); ?>">
                        <?php esc_html_e('Reset filters', 'bressol-theme'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($woo_active) : ?>
                <p class="bressol-pack-meta">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %d: number of packs */
                        _n('%d pakket gevonden', '%d pakketten gevonden', $found_posts, 'bressol-theme'),
                        $found_posts
                    ));
                    if ($has_filters) {
                        echo ' · ' . esc_html__('Gefilterd op:', 'bressol-theme') . ' ' . esc_html(implode(', ', $active_filters));
                    }
                    ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="bressol-section" id="pakketten-grid">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Pakketten', 'bressol-theme'); ?></h2>
            <p class="bressol-lead"><?php esc_html_e('Selecties met Valenciaanse signatuur, klaar om te kiezen of te verfijnen.', 'bressol-theme'); ?></p>
            <?php if (!$woo_active) : ?>
                <p class="bressol-empty"><?php esc_html_e('Het overzicht is tijdelijk niet beschikbaar. We helpen je graag met advies op maat.', 'bressol-theme'); ?></p>
                <p>
                    <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                </p>
            <?php else : ?>
                <?php if ($query->have_posts()) : ?>
                    <div class="bressol-grid bressol-pack-grid">
                        <?php while ($query->have_posts()) : ?>
                            <?php
                            $query->the_post();
                            $product = wc_get_product(get_the_ID());
                            if (!$product) {
                                continue;
                            }
                            $pack_json = (string) get_post_meta($product->get_id(), '_bressol_pack_definition', true);
                            $pack_def = $pack_json !== '' ? json_decode($pack_json, true) : null;
                            $is_personalizable = false;
                            if (is_array($pack_def) && !empty($pack_def['slots'])) {
                                foreach ($pack_def['slots'] as $slot) {
                                    if (!is_array($slot)) {
                                        continue;
                                    }
                                    $max = (int) ($slot['max'] ?? 1);
                                    $options = isset($slot['options']) && is_array($slot['options']) ? $slot['options'] : [];
                                    if ($max > 1 || count($options) > 1) {
                                        $is_personalizable = true;
                                        break;
                                    }
                                }
                            }
                            if ($is_personalizable && $personalizable_link === '') {
                                $personalizable_link = get_permalink($product->get_id());
                            }
                            $tier_value = sanitize_title((string) get_post_meta($product->get_id(), '_bressol_pack_tier', true));
                            $occasion_value = sanitize_title((string) get_post_meta($product->get_id(), '_bressol_pack_occasion', true));
                            $focus_value = sanitize_title((string) get_post_meta($product->get_id(), '_bressol_pack_focus', true));
                            $tier_label = $tier_value !== '' ? ($filter_map['tier'][$tier_value] ?? ucfirst(str_replace('-', ' ', $tier_value))) : '';
                            $occasion_label = $occasion_value !== '' ? ($filter_map['occasion'][$occasion_value] ?? ucfirst(str_replace('-', ' ', $occasion_value))) : '';
                            $focus_label = $focus_value !== '' ? ($filter_map['focus'][$focus_value] ?? ucfirst(str_replace('-', ' ', $focus_value))) : '';
                            $excerpt = $product->get_short_description();
                            $excerpt = $excerpt !== '' ? wp_trim_words(wp_strip_all_tags($excerpt), 16, '...') : '';
                            if ($excerpt === '') {
                                $fallback_bits = [];
                                if ($occasion_label !== '') {
                                    $fallback_bits[] = strtolower($occasion_label) . ' moment';
                                }
                                if ($focus_label !== '') {
                                    $fallback_bits[] = strtolower($focus_label);
                                }
                                $fallback = $fallback_bits
                                    ? 'Sobere Valenciaanse selectie: ' . implode(' · ', $fallback_bits) . '.'
                                    : 'Sobere Valenciaanse selectie met rustige balans.';
                                $excerpt = $fallback;
                            }
                            $price_html = $product->get_price_html();
                            ?>
                            <article class="bressol-card bressol-pack-card">
                                <a class="bressol-pack-card__media" href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                                    <?php echo wp_kses_post($product->get_image('medium')); ?>
                                </a>
                                <div class="bressol-pack-card__badges">
                                    <?php if ($is_personalizable) : ?>
                                        <span class="bressol-card__badge bressol-card__badge--primary"><?php esc_html_e('Personaliseerbaar', 'bressol-theme'); ?></span>
                                    <?php endif; ?>
                                    <span class="bressol-card__badge bressol-card__badge--secondary"><?php esc_html_e('Samenstelling', 'bressol-theme'); ?></span>
                                </div>
                                <h3 class="bressol-card__title">
                                    <a class="bressol-card__link" href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                                        <?php echo esc_html($product->get_name()); ?>
                                    </a>
                                </h3>
                                <p class="bressol-card__meta">
                                    <?php echo esc_html($excerpt); ?>
                                </p>
                                <?php if ($price_html !== '') : ?>
                                    <p class="bressol-pack-card__price"><?php echo wp_kses_post($price_html); ?></p>
                                <?php endif; ?>
                                <?php if ($tier_label || $occasion_label || $focus_label) : ?>
                                    <ul class="bressol-pack-card__attrs">
                                        <?php if ($tier_label) : ?>
                                            <li><?php echo esc_html($tier_label); ?></li>
                                        <?php endif; ?>
                                        <?php if ($occasion_label) : ?>
                                            <li><?php echo esc_html($occasion_label); ?></li>
                                        <?php endif; ?>
                                        <?php if ($focus_label) : ?>
                                            <li><?php echo esc_html($focus_label); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                <?php endif; ?>
                                <a class="bressol-link" href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                                    <?php esc_html_e('Bekijk pakket', 'bressol-theme'); ?>
                                </a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                    <?php if ($query->found_posts > 9) : ?>
                        <p class="bressol-pack-grid__more">
                            <a class="bressol-link" href="<?php echo esc_url($shop_link); ?>">
                                <?php esc_html_e('Bekijk alle pakketten', 'bressol-theme'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="bressol-empty"><?php esc_html_e('Er zijn op dit moment geen pakketten gevonden.', 'bressol-theme'); ?></p>
                    <p>
                        <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                            <?php esc_html_e('Vraag advies op maat', 'bressol-theme'); ?>
                        </a>
                    </p>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Personaliseerbaar', 'bressol-theme'); ?></h2>
            <p class="bressol-lead"><?php esc_html_e('Kies, selecteer en voeg toe — je ziet meteen wat past bij jouw moment.', 'bressol-theme'); ?></p>
            <div class="bressol-grid">
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Kies', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Begin met een basis die bij je moment past.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Selecteer', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Verfijn per slot met rustige keuzes.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Voeg toe', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Een samenstelling met Valenciaanse balans.', 'bressol-theme'); ?></p>
                </article>
            </div>
            <?php if (!empty($personalizable_link)) : ?>
                <div class="bressol-pack-feature">
                    <div>
                        <h3 class="bressol-card__title"><?php esc_html_e('Probeer personaliseren', 'bressol-theme'); ?></h3>
                        <p class="bressol-card__meta"><?php esc_html_e('Kies een pakket en verfijn rustig per slot.', 'bressol-theme'); ?></p>
                    </div>
                    <a class="bressol-button bressol-button--ghost" href="<?php echo esc_url($personalizable_link); ?>">
                        <?php esc_html_e('Bekijk pakket', 'bressol-theme'); ?>
                    </a>
                </div>
            <?php endif; ?>
            <div class="bressol-decision-panel">
                <div class="bressol-decision-panel__col">
                    <h3 class="bressol-card__title"><?php esc_html_e('Kies Pakketten', 'bressol-theme'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Snel kiezen met Valenciaanse basis', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Heldere prijs en samenstelling', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Direct personaliseerbaar', 'bressol-theme'); ?></li>
                    </ul>
                    <a class="bressol-button" href="<?php echo esc_url($filter_base . '#pakketten-grid'); ?>">
                        <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
                    </a>
                </div>
                <div class="bressol-decision-panel__col">
                    <h3 class="bressol-card__title"><?php esc_html_e('Kies Advies', 'bressol-theme'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Voor cadeau of twijfelgevallen', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Persoonlijke selectie op maat', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Rustige begeleiding in stappen', 'bressol-theme'); ?></li>
                    </ul>
                    <a class="bressol-button bressol-button--ghost" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php if ($show_faq) : ?>
        <section class="bressol-section">
            <div class="bressol-container">
                <h2 class="bressol-section-title"><?php esc_html_e('FAQ', 'bressol-theme'); ?></h2>
                <div class="bressol-prose">
                    <?php echo do_shortcode($page_content); ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="bressol-section">
        <div class="bressol-container">
            <div class="bressol-cta-panel">
                <div>
                    <h2 class="bressol-section-title"><?php esc_html_e('Kies met vertrouwen.', 'bressol-theme'); ?></h2>
                    <p class="bressol-lead"><?php esc_html_e('Advies geeft richting, pakketten geven rust.', 'bressol-theme'); ?></p>
                </div>
                <div>
                    <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                    <a class="bressol-button bressol-button--ghost" href="<?php echo esc_url($shop_link); ?>">
                        <?php esc_html_e('Bekijk shop', 'bressol-theme'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
