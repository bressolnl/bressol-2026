<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

do_action('woocommerce_before_main_content');
?>

<main id="main" class="bressol-main">
    <?php while (have_posts()) : ?>
        <?php the_post(); ?>

        <?php
        if (post_password_required()) {
            echo get_the_password_form();
            continue;
        }
        ?>

        <?php do_action('woocommerce_before_single_product'); ?>

        <section class="bressol-section bressol-hero bressol-hero--pdp">
            <div class="bressol-container bressol-hero__inner bressol-pdp__hero">
                <div class="bressol-pdp__media">
                    <div class="bressol-pdp__visual" aria-hidden="true"></div>
                    <?php do_action('woocommerce_before_single_product_summary'); ?>
                </div>
                <div class="summary entry-summary bressol-pdp__summary">
                    <?php do_action('woocommerce_single_product_summary'); ?>
                </div>
            </div>
        </section>

        <section class="bressol-section bressol-trust">
            <div class="bressol-container">
                <h2 class="bressol-section-title"><?php esc_html_e('Vertrouwd gekozen', 'bressol-theme'); ?></h2>
                <ul class="bressol-trust-bar">
                    <li><?php esc_html_e('Ambachtelijk geselecteerd', 'bressol-theme'); ?></li>
                    <li><?php esc_html_e('Valenciaanse oorsprong', 'bressol-theme'); ?></li>
                    <li><?php esc_html_e('Voor borrel, koken & cadeau', 'bressol-theme'); ?></li>
                    <li><?php esc_html_e('Zorgvuldig verpakt', 'bressol-theme'); ?></li>
                </ul>
            </div>
        </section>

        <section class="bressol-section">
            <div class="bressol-container">
                <h2 class="bressol-section-title"><?php esc_html_e('Smaak & gebruik', 'bressol-theme'); ?></h2>
                <div class="bressol-prose">
                    <ul>
                        <li><?php esc_html_e('Schenken bij borrel, tafel of als verfijnd cadeau.', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Combineer met mediterrane hapjes of rustige gerechten.', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Kies het moment en pas de selectie aan je gezelschap aan.', 'bressol-theme'); ?></li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="bressol-section">
            <div class="bressol-container">
                <h2 class="bressol-section-title"><?php esc_html_e('Pairing tips', 'bressol-theme'); ?></h2>
                <div class="bressol-prose">
                    <ul>
                        <li><?php esc_html_e('Borrel: combineer met olijven, noten en zachte kazen.', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Koken: sluit aan bij groenten, citrus en kruidige tonen.', 'bressol-theme'); ?></li>
                        <li><?php esc_html_e('Cadeau: kies een rustige selectie met elegante balans.', 'bressol-theme'); ?></li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="bressol-section">
            <div class="bressol-container">
                <h2 class="bressol-section-title"><?php esc_html_e('Oorsprong', 'bressol-theme'); ?></h2>
                <div class="bressol-prose">
                    <p><?php esc_html_e('De selectie weerspiegelt de mediterrane sfeer van Valencia: ambacht, ritme en een rustige smaakbalans. We kiezen op herkomst en stijl, met respect voor traditie en een moderne, sobere uitstraling.', 'bressol-theme'); ?></p>
                </div>
            </div>
        </section>

        <section class="bressol-section">
            <div class="bressol-container">
                <h2 class="bressol-section-title"><?php esc_html_e('Bewaren', 'bressol-theme'); ?></h2>
                <div class="bressol-prose">
                    <p><?php esc_html_e('Bewaar op een koele, droge plek en geniet op het moment dat het past.', 'bressol-theme'); ?></p>
                </div>
            </div>
        </section>

        <section class="bressol-section bressol-pdp__after">
            <div class="bressol-container">
                <?php do_action('woocommerce_after_single_product_summary'); ?>
            </div>
        </section>

        <?php do_action('woocommerce_after_single_product'); ?>
    <?php endwhile; ?>
</main>

<?php
do_action('woocommerce_after_main_content');

get_footer();
