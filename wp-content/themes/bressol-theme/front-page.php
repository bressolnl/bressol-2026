<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$advies_link = bressol_get_page_link('advies', '/');
$pakketten_link = bressol_get_page_link('pakketten', '/');
$moments_link = bressol_get_page_link('momenten', '/');
$oorsprong_link = '';
if (function_exists('bressol_get_page_link')) {
    $oorsprong_link = bressol_get_page_link('oorsprong', '');
}
if ($oorsprong_link === '') {
    $origin_page = get_page_by_path('oorsprong');
    if ($origin_page instanceof WP_Post) {
        $origin_link = get_permalink($origin_page);
        if (is_string($origin_link) && $origin_link !== '') {
            $oorsprong_link = $origin_link;
        }
    }
}
$shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
$shop_link = $shop_page_id ? get_permalink($shop_page_id) : home_url('/');
$woo_active = class_exists('WooCommerce');
$front_id = (int) (get_option('page_on_front') ?: get_queried_object_id());
$hero_image = $front_id ? get_the_post_thumbnail_url($front_id, 'full') : '';
$hero_style = $hero_image ? ' style="background-image:url(' . esc_url($hero_image) . ');"' : '';
$moments = get_terms([
    'taxonomy' => 'bressol_moment',
    'hide_empty' => false,
    'number' => 6,
    'orderby' => 'term_order',
    'order' => 'ASC',
]);
$moment_placeholders = [
    'Aperitief',
    'Diner',
    'Cadeau',
    'Weekend',
    'Feest',
    'Zakelijk',
];
if (!is_wp_error($moments) && !empty($moments)) {
    $has_term_order = false;
    foreach ($moments as $moment) {
        if (isset($moment->term_order) && (int) $moment->term_order > 0) {
            $has_term_order = true;
            break;
        }
    }
    if (!$has_term_order) {
        usort($moments, function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });
    }
}
?>

<main id="main" class="bressol-main">
    <section class="bressol-section bressol-hero bressol-hero--home">
        <div class="bressol-container bressol-hero__inner">
            <div class="bressol-hero__content">
                <h1 class="bressol-title"><?php esc_html_e('Valenciaanse smaken, rustig gekozen.', 'bressol-theme'); ?></h1>
                <span class="bressol-hero__accent" aria-hidden="true"></span>
                <p class="bressol-lead"><?php esc_html_e('Bressol selecteert delicatessen met ambachtelijke herkomst en een sobere, mediterrane stijl.', 'bressol-theme'); ?></p>
                <div class="bressol-cta-panel">
                    <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                    <a class="bressol-button bressol-button--ghost" href="<?php echo esc_url($pakketten_link); ?>">
                        <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
                    </a>
                </div>
                <p class="bressol-hero__note"><?php esc_html_e('Kies je moment — wij helpen je verfijnen.', 'bressol-theme'); ?></p>
            </div>
            <div class="bressol-hero__visual" aria-hidden="true"<?php echo $hero_style; ?>></div>
        </div>
    </section>

    <section class="bressol-section bressol-trust">
        <div class="bressol-container">
            <ul class="bressol-trust-bar">
                <li><?php esc_html_e('Herkomst met karakter', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Ambachtelijk gekozen', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Evenwichtige smaak', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Rustige presentatie', 'bressol-theme'); ?></li>
            </ul>
        </div>
    </section>

    <section class="bressol-section bressol-sale">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Aanbiedingen', 'bressol-theme'); ?></h2>
            <p class="bressol-lead"><?php esc_html_e('Tijdelijk voordeel op een sobere selectie.', 'bressol-theme'); ?></p>
            <?php if ($woo_active) : ?>
                <?php
                $sale_html = (string) do_shortcode('[sale_products limit="4" columns="4" orderby="date" order="DESC"]');
                ?>
                <?php if (trim($sale_html) !== '') : ?>
                    <div class="bressol-sale__grid">
                        <?php echo $sale_html; ?>
                    </div>
                <?php else : ?>
                    <p class="bressol-empty"><?php esc_html_e('Geen aanbiedingen op dit moment. Ontdek de selectie op jouw moment.', 'bressol-theme'); ?></p>
                    <p>
                        <a class="bressol-link" href="<?php echo esc_url($shop_link); ?>">
                            <?php esc_html_e('Ontdek de shop', 'bressol-theme'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <p class="bressol-empty"><?php esc_html_e('Aanbiedingen zijn momenteel niet beschikbaar.', 'bressol-theme'); ?></p>
                <p>
                    <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="bressol-section bressol-why">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Waarom Bressol', 'bressol-theme'); ?></h2>
            <div class="bressol-grid bressol-why__grid">
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Herkomst met ritme', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Kleine makers uit Valencia, gekozen op continuïteit en vakmanschap.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Sober geselecteerd', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Selecties met balans, passend bij tafel en moment.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Rustige presentatie', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Een mediterrane stijl zonder ruis of overdaad.', 'bressol-theme'); ?></p>
                </article>
            </div>
        </div>
    </section>

    <section class="bressol-section bressol-steps">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Hoe werkt advies', 'bressol-theme'); ?></h2>
            <div class="bressol-prose">
                <ol>
                    <li><?php esc_html_e('Kies het moment dat je wilt vieren.', 'bressol-theme'); ?></li>
                    <li><?php esc_html_e('Geef je smaak en stijl aan.', 'bressol-theme'); ?></li>
                    <li><?php esc_html_e('Ontvang een selectie die past bij je tafel.', 'bressol-theme'); ?></li>
                </ol>
            </div>
            <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
            </a>
        </div>
    </section>

    <section class="bressol-section bressol-categories">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Categorieën', 'bressol-theme'); ?></h2>
            <p class="bressol-lead"><?php esc_html_e('Ontdek per categorie en kies je stijl.', 'bressol-theme'); ?></p>
            <?php if ($woo_active) : ?>
                <?php
                $categories = get_terms([
                    'taxonomy' => 'product_cat',
                    'parent' => 0,
                    'hide_empty' => false,
                    'number' => 6,
                    'orderby' => 'menu_order',
                    'order' => 'ASC',
                ]);
                if (!is_wp_error($categories)) {
                    $categories = array_filter($categories, function ($term) {
                        return $term instanceof WP_Term && $term->slug !== 'uncategorized';
                    });
                } else {
                    $categories = [];
                }
                $category_fallbacks = [
                    esc_html__('Voor koken en afwerking.', 'bressol-theme'),
                    esc_html__('Voor borrel en tafel.', 'bressol-theme'),
                    esc_html__('Voor cadeau en sfeer.', 'bressol-theme'),
                    esc_html__('Voor rustige combinaties.', 'bressol-theme'),
                    esc_html__('Voor selecties met balans.', 'bressol-theme'),
                    esc_html__('Voor mediterrane accenten.', 'bressol-theme'),
                ];
                ?>
                <?php if (!empty($categories)) : ?>
                    <div class="bressol-grid bressol-categories__grid">
                        <?php foreach (array_values($categories) as $index => $term) : ?>
                            <?php
                            $term_link = get_term_link($term);
                            $thumb_id = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
                            $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';
                            $desc = wp_trim_words(wp_strip_all_tags((string) $term->description), 16, '...');
                            $fallback_desc = $category_fallbacks[$index] ?? esc_html__('Een rustige selectie voor elk moment.', 'bressol-theme');
                            ?>
                            <article class="bressol-card bressol-card--media">
                                <a class="bressol-card__media" href="<?php echo esc_url($term_link); ?>" aria-hidden="true">
                                    <?php if ($thumb_url) : ?>
                                        <img src="<?php echo esc_url($thumb_url); ?>" alt="" loading="lazy">
                                    <?php else : ?>
                                        <span class="bressol-card__media-placeholder"></span>
                                    <?php endif; ?>
                                </a>
                                <h3 class="bressol-card__title">
                                    <a class="bressol-card__link" href="<?php echo esc_url($term_link); ?>">
                                        <?php echo esc_html($term->name); ?>
                                    </a>
                                </h3>
                                <p class="bressol-card__meta">
                                    <?php echo esc_html($desc ?: $fallback_desc); ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="bressol-grid bressol-categories__grid">
                        <?php foreach (['Selectie', 'Borrel', 'Cadeau'] as $label) : ?>
                            <article class="bressol-card bressol-card--media">
                                <span class="bressol-card__media bressol-card__media-placeholder" aria-hidden="true"></span>
                                <h3 class="bressol-card__title"><?php echo esc_html($label); ?></h3>
                                <p class="bressol-card__meta"><?php esc_html_e('Premium selectie per categorie.', 'bressol-theme'); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <p>
                        <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>">
                            <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <div class="bressol-grid bressol-categories__grid">
                    <?php foreach (['Selectie', 'Borrel', 'Cadeau'] as $label) : ?>
                        <article class="bressol-card bressol-card--media">
                            <span class="bressol-card__media bressol-card__media-placeholder" aria-hidden="true"></span>
                            <h3 class="bressol-card__title"><?php echo esc_html($label); ?></h3>
                            <p class="bressol-card__meta"><?php esc_html_e('Premium selectie per categorie.', 'bressol-theme'); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
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
            <h2 class="bressol-section-title"><?php esc_html_e('Voor elk moment', 'bressol-theme'); ?></h2>
            <p class="bressol-lead"><?php esc_html_e('Kies een context en ontdek wat past bij de tafel.', 'bressol-theme'); ?></p>
            <div class="bressol-grid">
                <?php if (!is_wp_error($moments) && !empty($moments)) : ?>
                    <?php foreach ($moments as $moment) : ?>
                        <?php $moment_link = get_term_link($moment); ?>
                        <?php $moment_desc = wp_trim_words(wp_strip_all_tags($moment->description), 16, '...'); ?>
                        <article class="bressol-card">
                            <h3 class="bressol-card__title">
                                <a class="bressol-card__link" href="<?php echo esc_url($moment_link); ?>">
                                    <?php echo esc_html($moment->name); ?>
                                </a>
                            </h3>
                            <p class="bressol-card__meta">
                                <?php echo esc_html($moment_desc ?: esc_html__('Rustige mediterrane selectie voor dit moment.', 'bressol-theme')); ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?php foreach ($moment_placeholders as $placeholder) : ?>
                        <article class="bressol-card">
                            <h3 class="bressol-card__title"><?php echo esc_html($placeholder); ?></h3>
                            <p class="bressol-card__meta"><?php esc_html_e('Selecties voor sfeer, tafel en cadeau.', 'bressol-theme'); ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p>
                <a class="bressol-link" href="<?php echo esc_url($moments_link); ?>">
                    <?php esc_html_e('Ontdek alle momenten', 'bressol-theme'); ?>
                </a>
            </p>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Populaire keuzes', 'bressol-theme'); ?></h2>
            <p class="bressol-lead"><?php esc_html_e('Drie samengestelde pakketten als rustige start.', 'bressol-theme'); ?></p>
            <div class="bressol-grid">
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Signatuur', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Compact, verfijnd en in balans.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Feestelijk', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Voor een tafel met warme accenten.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Cadeau', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Elegante gift met rustige stijl.', 'bressol-theme'); ?></p>
                </article>
            </div>
            <p>
                <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>">
                    <?php esc_html_e('Bekijk alle pakketten', 'bressol-theme'); ?>
                </a>
            </p>
        </div>
    </section>

    <section class="bressol-section bressol-origin-quality">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Onze oorsprong & kwaliteit', 'bressol-theme'); ?></h2>
            <div class="bressol-prose">
                <p><?php esc_html_e('Bressol werkt met Valenciaanse makers die het ritme van tafel en borrel respecteren. We kiezen sober, met aandacht voor herkomst en ambacht.', 'bressol-theme'); ?></p>
                <p><?php esc_html_e('Een mediterrane selectie, rustig gepresenteerd en in balans.', 'bressol-theme'); ?></p>
            </div>
            <ul class="bressol-origin-quality__list">
                <li><?php esc_html_e('Herkomst met karakter', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Selectie met rust', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Zorgvuldige presentatie', 'bressol-theme'); ?></li>
            </ul>
            <?php if ($oorsprong_link !== '') : ?>
                <p>
                    <a class="bressol-link" href="<?php echo esc_url($oorsprong_link); ?>">
                        <?php esc_html_e('Lees meer over onze oorsprong', 'bressol-theme'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="bressol-section bressol-agenda">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Agenda', 'bressol-theme'); ?></h2>
            <p class="bressol-lead"><?php esc_html_e('De komende markten en events waar je ons ontmoet.', 'bressol-theme'); ?></p>
            <?php if (shortcode_exists('bressol_upcoming_events')) : ?>
                <?php echo do_shortcode('[bressol_upcoming_events limit="4"]'); ?>
            <?php else : ?>
                <div class="bressol-agenda">
                    <div class="bressol-agenda__empty">
                        <p class="bressol-empty"><?php esc_html_e('Geen bevestigde markten of events gepland.', 'bressol-theme'); ?></p>
                        <div class="bressol-agenda__cta">
                            <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                                <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                            </a>
                            <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>">
                                <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($moments_link !== '') : ?>
                <p class="bressol-agenda__more">
                    <a class="bressol-link" href="<?php echo esc_url($moments_link); ?>">
                        <?php esc_html_e('Bekijk agenda', 'bressol-theme'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </section>

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
                    <a class="bressol-button bressol-button--ghost" href="<?php echo esc_url($pakketten_link); ?>">
                        <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
