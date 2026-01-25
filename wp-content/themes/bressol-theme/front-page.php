<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$advies_link = bressol_get_page_link('advies', '/');
$pakketten_link = bressol_get_page_link('pakketten', '/');
$moments_link = bressol_get_page_link('momenten', '/');
$shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
$shop_link = $shop_page_id ? get_permalink($shop_page_id) : home_url('/');
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
                <h1 class="bressol-title"><?php esc_html_e('Mediterrane selecties met Valencian karakter.', 'bressol-theme'); ?></h1>
                <p class="bressol-lead"><?php esc_html_e('Sober, verfijnd en zorgvuldig gekozen voor borrel, cadeau of tafel.', 'bressol-theme'); ?></p>
                <div class="bressol-cta-panel">
                    <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                    <a class="bressol-link" href="<?php echo esc_url($shop_link); ?>">
                        <?php esc_html_e('Bekijk de shop', 'bressol-theme'); ?>
                    </a>
                </div>
            </div>
            <div class="bressol-hero__visual" aria-hidden="true"></div>
        </div>
    </section>

    <section class="bressol-section bressol-trust">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Waarom Bressol', 'bressol-theme'); ?></h2>
            <ul class="bressol-trust-bar">
                <li><?php esc_html_e('Herkomst met karakter', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Ambachtelijk gekozen', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Evenwichtige smaak', 'bressol-theme'); ?></li>
            </ul>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Onze oorsprong', 'bressol-theme'); ?></h2>
            <div class="bressol-prose">
                <p><?php esc_html_e('Bressol werkt met kleine makers en familiebedrijven uit Valencia. We kiezen op herkomst, ritme en ambacht, met respect voor traditie en een moderne, sobere stijl.', 'bressol-theme'); ?></p>
            </div>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Populaire keuzes', 'bressol-theme'); ?></h2>
            <div class="bressol-grid">
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Signatuur', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Een compacte selectie met een verfijnde kern.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Feestelijk', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Voor een tafel met warmte en mediterrane accenten.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Cadeau', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Een elegante gift met karakter en balans.', 'bressol-theme'); ?></p>
                </article>
            </div>
            <p>
                <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>">
                    <?php esc_html_e('Bekijk alle pakketten', 'bressol-theme'); ?>
                </a>
            </p>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Voor elk moment', 'bressol-theme'); ?></h2>
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
                                <?php echo esc_html($moment_desc ?: esc_html__('Een moment met rustige mediterrane flair.', 'bressol-theme')); ?>
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
            <h2 class="bressol-section-title"><?php esc_html_e('Kwaliteit zonder compromis', 'bressol-theme'); ?></h2>
            <div class="bressol-prose">
                <p><?php esc_html_e('We selecteren op herkomst, vakmanschap en een rustige smaakbalans. Elk pakket is zorgvuldig samengesteld en met aandacht verpakt.', 'bressol-theme'); ?></p>
            </div>
            <ul class="bressol-trust-bar">
                <li><?php esc_html_e('Curatie per seizoen', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Sober & smaakvol', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Zorgvuldig verpakt', 'bressol-theme'); ?></li>
            </ul>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <div class="bressol-cta-panel">
                <div>
                    <h2 class="bressol-section-title"><?php esc_html_e('Klaar om te kiezen?', 'bressol-theme'); ?></h2>
                    <p class="bressol-lead"><?php esc_html_e('Laat je adviseren of bekijk de pakketten.', 'bressol-theme'); ?></p>
                </div>
                <div>
                    <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                    <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>">
                        <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
