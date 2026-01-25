<?php
/**
 * Template Name: Advies
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="bressol-main">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : ?>
            <?php the_post(); ?>

            <div class="bressol-container">
                <header class="bressol-hero bressol-section">
                    <h1 class="bressol-hero__title"><?php the_title(); ?></h1>
                    <p class="bressol-lead">
                        <?php esc_html_e('Ontvang persoonlijk advies en ontdek de perfecte combinatie voor jouw moment.', 'bressol-theme'); ?>
                    </p>
                </header>

                <section class="bressol-section bressol-content">
                    <?php the_content(); ?>
                </section>

                <section class="bressol-section bressol-faq">
                    <h2 class="bressol-section__title"><?php esc_html_e('Veelgestelde vragen', 'bressol-theme'); ?></h2>
                    <div class="bressol-faq__item">
                        <h3><?php esc_html_e('Hoe werkt het advies?', 'bressol-theme'); ?></h3>
                        <p><?php esc_html_e('Voorbeeldtekst voor een korte uitleg van het adviesproces.', 'bressol-theme'); ?></p>
                    </div>
                    <div class="bressol-faq__item">
                        <h3><?php esc_html_e('Kan ik mijn keuze later aanpassen?', 'bressol-theme'); ?></h3>
                        <p><?php esc_html_e('Voorbeeldtekst voor een antwoord over aanpassingen.', 'bressol-theme'); ?></p>
                    </div>
                </section>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php
get_footer();
