<?php
/**
 * Template Name: Momenten
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$advies_link = bressol_get_page_link('advies', '/');
$pakketten_link = bressol_get_page_link('pakketten', '/');
$moments = get_terms([
    'taxonomy' => 'bressol_moment',
    'hide_empty' => false,
    'number' => 12,
    'orderby' => 'term_order',
    'order' => 'ASC',
]);

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
    <section class="bressol-section bressol-hero bressol-hero--plp">
        <div class="bressol-container bressol-hero__inner">
            <div class="bressol-hero__content">
                <h1 class="bressol-title"><?php esc_html_e('Momenten met mediterrane flair.', 'bressol-theme'); ?></h1>
                <p class="bressol-lead">
                    <?php esc_html_e('Van borrel tot cadeau en tafel: ontdek selecties die passen bij de sfeer van het moment.', 'bressol-theme'); ?>
                </p>
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
            <h2 class="bressol-section-title"><?php esc_html_e('Kies met vertrouwen', 'bressol-theme'); ?></h2>
            <ul class="bressol-trust-bar">
                <li><?php esc_html_e('Herkomst met karakter', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Ambachtelijke selectie', 'bressol-theme'); ?></li>
                <li><?php esc_html_e('Smaak in balans', 'bressol-theme'); ?></li>
            </ul>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Voor elk moment', 'bressol-theme'); ?></h2>
            <?php if (!is_wp_error($moments) && !empty($moments)) : ?>
                <div class="bressol-grid">
                    <?php foreach ($moments as $moment) : ?>
                        <?php $moment_link = get_term_link($moment); ?>
                        <?php $moment_desc = wp_trim_words(wp_strip_all_tags($moment->description), 18, '...'); ?>
                        <article class="bressol-card">
                            <h2 class="bressol-card__title">
                                <a class="bressol-card__link" href="<?php echo esc_url($moment_link); ?>">
                                    <?php echo esc_html($moment->name); ?>
                                </a>
                            </h2>
                            <p class="bressol-card__meta">
                                <?php echo esc_html($moment_desc ?: esc_html__('Een selectie met mediterrane nuance en balans.', 'bressol-theme')); ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="bressol-empty">
                    <?php esc_html_e('Er zijn nog geen momenten beschikbaar. Start met advies voor een selectie op maat.', 'bressol-theme'); ?>
                </p>
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
            <div class="bressol-cta-panel">
                <div>
                    <h2 class="bressol-section-title"><?php esc_html_e('Hulp nodig bij kiezen?', 'bressol-theme'); ?></h2>
                    <p class="bressol-lead"><?php esc_html_e('We helpen je graag met advies dat past bij jouw moment.', 'bressol-theme'); ?></p>
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
