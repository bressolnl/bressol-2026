<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$pakketten_link = bressol_get_page_link('pakketten', '/');
?>

<main class="bressol-main">
    <section class="bressol-section" data-track-section="advies-hero">
        <div class="bressol-container">
            <h1 class="bressol-title"><?php esc_html_e('Advies op maat', 'bressol-theme'); ?></h1>
            <p class="bressol-lead">
                <?php esc_html_e('Beantwoord enkele korte vragen en ontvang een selectie die past bij jouw moment.', 'bressol-theme'); ?>
            </p>
        </div>
    </section>

    <section class="bressol-section" data-track-section="advies-formulier">
        <div class="bressol-container">
            <div class="bressol-form-shell" data-track-form="advies">
                <?php echo do_shortcode('[bressol_advice_form]'); ?>
            </div>
        </div>
    </section>

    <section class="bressol-section" data-track-section="advies-faq">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Veelgestelde vragen', 'bressol-theme'); ?></h2>
            <div class="bressol-faq">
                <article class="bressol-faq__item">
                    <h3 class="bressol-faq__question"><?php esc_html_e('Hoe snel ontvang ik mijn advies?', 'bressol-theme'); ?></h3>
                    <p class="bressol-faq__answer"><?php esc_html_e('Je ontvangt direct een voorstel op basis van je antwoorden.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-faq__item">
                    <h3 class="bressol-faq__question"><?php esc_html_e('Kan ik het advies aanpassen?', 'bressol-theme'); ?></h3>
                    <p class="bressol-faq__answer"><?php esc_html_e('Ja, je kunt pakketten vergelijken en handmatig bijstellen.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-faq__item">
                    <h3 class="bressol-faq__question"><?php esc_html_e('Is het advies gratis?', 'bressol-theme'); ?></h3>
                    <p class="bressol-faq__answer"><?php esc_html_e('Het advies is volledig vrijblijvend en kosteloos.', 'bressol-theme'); ?></p>
                </article>
            </div>
        </div>
    </section>

    <section class="bressol-section" data-track-section="advies-cta">
        <div class="bressol-container">
            <div class="bressol-cta-panel">
                <div>
                    <h2 class="bressol-section-title"><?php esc_html_e('Lievers meteen kiezen?', 'bressol-theme'); ?></h2>
                    <p class="bressol-lead"><?php esc_html_e('Bekijk alle pakketten en ontdek het assortiment.', 'bressol-theme'); ?></p>
                </div>
                <a class="bressol-button" href="<?php echo esc_url($pakketten_link); ?>" data-track-cta="pakketten">
                    <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
                </a>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
