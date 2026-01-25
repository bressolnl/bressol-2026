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
$over_ons_link = bressol_get_page_link('over-ons', '/');
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

<main class="bressol-main">
    <section class="bressol-section">
        <div class="bressol-container">
            <h1 class="bressol-title"><?php esc_html_e('Mediterrane momenten, zorgvuldig gekozen.', 'bressol-theme'); ?></h1>
            <p class="bressol-lead"><?php esc_html_e('Een verfijnde selectie voor tafel, cadeau of aperitief.', 'bressol-theme'); ?></p>
            <div class="bressol-cta-panel">
                <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                    <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                </a>
                <a class="bressol-link" href="<?php echo esc_url($shop_link); ?>">
                    <?php esc_html_e('Bekijk de shop', 'bressol-theme'); ?>
                </a>
            </div>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Kies jouw moment', 'bressol-theme'); ?></h2>
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
                                <?php echo esc_html($moment_desc ?: esc_html__('Een selectie met karakter en balans.', 'bressol-theme')); ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?php foreach ($moment_placeholders as $placeholder) : ?>
                        <article class="bressol-card">
                            <h3 class="bressol-card__title"><?php echo esc_html($placeholder); ?></h3>
                            <p class="bressol-card__meta"><?php esc_html_e('Rustige, verfijnde combinaties.', 'bressol-theme'); ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p>
                <a class="bressol-link" href="<?php echo esc_url($moments_link); ?>">
                    <?php esc_html_e('Alle momenten bekijken', 'bressol-theme'); ?>
                </a>
            </p>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Pakketten', 'bressol-theme'); ?></h2>
            <div class="bressol-grid">
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Signatuur', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Een compact pakket met een verfijnde kern.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Feestelijk', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Ideaal voor tafelmomenten en aperitief.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Cadeau', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Een elegante gift met authentieke smaken.', 'bressol-theme'); ?></p>
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
            <h2 class="bressol-section-title"><?php esc_html_e('Oorsprong', 'bressol-theme'); ?></h2>
            <article class="bressol-card">
                <p class="bressol-card__meta"><?php esc_html_e('Selecties met herkomst, ritme en karakter.', 'bressol-theme'); ?></p>
                <?php if ($over_ons_link) : ?>
                    <a class="bressol-link" href="<?php echo esc_url($over_ons_link); ?>">
                        <?php esc_html_e('Lees meer over ons', 'bressol-theme'); ?>
                    </a>
                <?php else : ?>
                    <span class="bressol-link"><?php esc_html_e('Binnenkort meer over onze oorsprong.', 'bressol-theme'); ?></span>
                <?php endif; ?>
            </article>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Blijf op de hoogte', 'bressol-theme'); ?></h2>
            <article class="bressol-card">
                <p class="bressol-card__meta"><?php esc_html_e('Updates over nieuwe selecties en seizoensmomenten.', 'bressol-theme'); ?></p>
                <form class="bressol-form-shell">
                    <label class="screen-reader-text" for="bressol-newsletter-email"><?php esc_html_e('E-mailadres', 'bressol-theme'); ?></label>
                    <input id="bressol-newsletter-email" type="email" placeholder="<?php esc_attr_e('E-mailadres', 'bressol-theme'); ?>">
                    <button class="bressol-button" type="button"><?php esc_html_e('Meld je aan', 'bressol-theme'); ?></button>
                </form>
            </article>
        </div>
    </section>
</main>

<?php
get_footer();
