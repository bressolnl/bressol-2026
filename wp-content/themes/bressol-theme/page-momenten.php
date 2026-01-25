<?php
/**
 * Template Name: Momenten
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$advies_link = bressol_get_page_link('advies', '/');
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
    <section class="bressol-section">
        <div class="bressol-container">
            <h1 class="bressol-title"><?php esc_html_e('Momenten', 'bressol-theme'); ?></h1>
            <p class="bressol-lead">
                <?php esc_html_e('Ontdek welke selectie past bij het moment dat je wilt vieren.', 'bressol-theme'); ?>
            </p>
            <p>
                <a class="bressol-link" href="<?php echo esc_url($advies_link); ?>">
                    <?php esc_html_e('Liever advies op maat?', 'bressol-theme'); ?>
                </a>
            </p>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
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
                                <?php echo esc_html($moment_desc ?: esc_html__('Een selectie met karakter en balans.', 'bressol-theme')); ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="bressol-empty">
                    <?php esc_html_e('Er zijn nog geen momenten toegevoegd.', 'bressol-theme'); ?>
                </p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
get_footer();
