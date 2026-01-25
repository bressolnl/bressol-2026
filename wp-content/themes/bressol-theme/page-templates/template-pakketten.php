<?php
/**
 * Template Name: Pakketten landing
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$advies_link = home_url('/advies/');
$moment_terms = get_terms([
    'taxonomy' => 'bressol_moment',
    'hide_empty' => false,
    'number' => 4,
]);
$category_links = [];
$category_slugs = [
    'packs' => esc_html__('Alle pakketten', 'bressol-theme'),
    'drinks' => esc_html__('Dranken', 'bressol-theme'),
    'oil' => esc_html__('Oliën', 'bressol-theme'),
];
foreach ($category_slugs as $slug => $label) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if ($term && !is_wp_error($term)) {
        $category_links[] = [
            'label' => $label,
            'link' => get_term_link($term),
        ];
    }
}
$packs_link = '';
$packs_term = get_term_by('slug', 'packs', 'product_cat');
if ($packs_term) {
    $packs_link = get_term_link($packs_term);
}
if (!$packs_link || is_wp_error($packs_link)) {
    $shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
    $packs_link = $shop_page_id ? get_permalink($shop_page_id) : home_url('/');
}
?>

<main class="bressol-main">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : ?>
            <?php the_post(); ?>

            <div class="bressol-container">
                <header class="bressol-hero bressol-section">
                    <h1 class="bressol-hero__title"><?php the_title(); ?></h1>
                    <p class="bressol-lead">
                        <?php esc_html_e('Ambachtelijk samengesteld, met aandacht voor herkomst en balans.', 'bressol-theme'); ?>
                    </p>
                    <p class="bressol-lead">
                        <?php esc_html_e('Een redactionele selectie voor wie kwaliteit, rust en authenticiteit zoekt.', 'bressol-theme'); ?>
                    </p>
                    <div class="bressol-hero__actions">
                        <a class="bressol-cta" href="<?php echo esc_url($advies_link); ?>">
                            <?php esc_html_e('Start met advies op maat', 'bressol-theme'); ?>
                        </a>
                        <a class="bressol-link" href="<?php echo esc_url($packs_link); ?>">
                            <?php esc_html_e('Bekijk alle pakketten', 'bressol-theme'); ?>
                        </a>
                    </div>
                </header>

                <section class="bressol-section bressol-content">
                    <?php the_content(); ?>
                </section>

                <section class="bressol-section">
                    <h2 class="bressol-section__title"><?php esc_html_e('Voor wie is dit?', 'bressol-theme'); ?></h2>
                    <ul class="bressol-list">
                        <li><?php esc_html_e('Voor wie snel wil kiezen zonder in te leveren op kwaliteit.', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Voor cadeaus die verfijnd en persoonlijk aanvoelen.', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Voor momenten die vragen om een doordachte selectie.', 'bressol-theme'); ?></li>
                    </ul>
                </section>

                <section class="bressol-section">
                    <h2 class="bressol-section__title"><?php esc_html_e('Uitgelichte pakketten', 'bressol-theme'); ?></h2>
                    <ul class="bressol-list">
                        <li><?php esc_html_e('Voorbeeld: pakket voor een feestelijk moment', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Voorbeeld: premium selectie', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Voorbeeld: cadeau voor fijnproevers', 'bressol-theme'); ?></li>
                    </ul>
                </section>

                <section class="bressol-section">
                    <h2 class="bressol-section__title"><?php esc_html_e('Snelle routes', 'bressol-theme'); ?></h2>
                    <div class="bressol-grid">
                        <?php if (!is_wp_error($moment_terms) && !empty($moment_terms)) : ?>
                            <?php foreach ($moment_terms as $moment) : ?>
                                <?php $moment_link = get_term_link($moment); ?>
                                <article class="bressol-card">
                                    <h3 class="bressol-card__title">
                                        <a class="bressol-card__link" href="<?php echo esc_url($moment_link); ?>">
                                            <?php echo esc_html($moment->name); ?>
                                        </a>
                                    </h3>
                                    <p class="bressol-card__meta"><?php esc_html_e('Korte route naar een passende selectie.', 'bressol-theme'); ?></p>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <article class="bressol-card"><h3 class="bressol-card__title"><?php esc_html_e('Aperitief', 'bressol-theme'); ?></h3><p class="bressol-card__meta"><?php esc_html_e('Licht en uitnodigend.', 'bressol-theme'); ?></p></article>
                            <article class="bressol-card"><h3 class="bressol-card__title"><?php esc_html_e('Diner', 'bressol-theme'); ?></h3><p class="bressol-card__meta"><?php esc_html_e('Verfijnde tafelmomenten.', 'bressol-theme'); ?></p></article>
                            <article class="bressol-card"><h3 class="bressol-card__title"><?php esc_html_e('Cadeau', 'bressol-theme'); ?></h3><p class="bressol-card__meta"><?php esc_html_e('Een elegante geste.', 'bressol-theme'); ?></p></article>
                        <?php endif; ?>
                        <?php foreach ($category_links as $category_link) : ?>
                            <article class="bressol-card">
                                <h3 class="bressol-card__title">
                                    <a class="bressol-card__link" href="<?php echo esc_url($category_link['link']); ?>">
                                        <?php echo esc_html($category_link['label']); ?>
                                    </a>
                                </h3>
                                <p class="bressol-card__meta"><?php esc_html_e('Ontdek meer binnen dit thema.', 'bressol-theme'); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <p>
                    <a class="bressol-cta" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Ontvang persoonlijk advies', 'bressol-theme'); ?>
                    </a>
                    <a class="bressol-link" href="<?php echo esc_url($packs_link); ?>">
                        <?php esc_html_e('Bekijk de shop', 'bressol-theme'); ?>
                    </a>
                </p>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php
get_footer();
