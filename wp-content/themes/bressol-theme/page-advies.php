<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$pakketten_link = bressol_get_page_link('pakketten', '/');
$advies_link = '#advies-formulier';
?>

<main class="bressol-main">
    <section class="bressol-section bressol-hero bressol-hero--plp" data-track-section="advies-hero">
        <div class="bressol-container bressol-hero__inner">
            <div class="bressol-hero__content">
                <h1 class="bressol-title"><?php esc_html_e('Advies op maat voor jouw moment.', 'bressol-theme'); ?></h1>
                <p class="bressol-lead">
                    <?php esc_html_e('Een korte vragenlijst helpt ons je stijl en het moment te begrijpen. Je krijgt direct een selectie die rustig en passend aanvoelt.', 'bressol-theme'); ?>
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

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Hoe het werkt', 'bressol-theme'); ?></h2>
            <div class="bressol-grid">
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('1. Vertel je moment', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Kies of het om borrel, cadeau of tafel gaat.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('2. Kies je stijl', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Geef je voorkeuren aan voor smaak en sfeer.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('3. Ontvang je selectie', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Direct een voorstel dat je kunt vergelijken of aanpassen.', 'bressol-theme'); ?></p>
                </article>
            </div>
        </div>
    </section>

    <section class="bressol-section">
        <div class="bressol-container">
            <h2 class="bressol-section-title"><?php esc_html_e('Voor wie', 'bressol-theme'); ?></h2>
            <div class="bressol-grid">
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Cadeau', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Een doordachte selectie met een elegante uitstraling.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Borrel', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Rustige combinaties die passen bij het moment.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-card">
                    <h3 class="bressol-card__title"><?php esc_html_e('Koken & tafel', 'bressol-theme'); ?></h3>
                    <p class="bressol-card__meta"><?php esc_html_e('Selecties die aansluiten op jouw tafel en ritme.', 'bressol-theme'); ?></p>
                </article>
            </div>
        </div>
    </section>

    <section class="bressol-section" id="advies-formulier" data-track-section="advies-formulier">
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
                    <h3 class="bressol-faq__q"><?php esc_html_e('Hoe snel ontvang ik mijn advies?', 'bressol-theme'); ?></h3>
                    <p class="bressol-faq__a"><?php esc_html_e('Direct na het invullen tonen we een selectie op basis van je antwoorden.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-faq__item">
                    <h3 class="bressol-faq__q"><?php esc_html_e('Kan ik het advies aanpassen?', 'bressol-theme'); ?></h3>
                    <p class="bressol-faq__a"><?php esc_html_e('Ja, je kunt pakketten vergelijken en zelf verfijnen.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-faq__item">
                    <h3 class="bressol-faq__q"><?php esc_html_e('Is het advies gratis?', 'bressol-theme'); ?></h3>
                    <p class="bressol-faq__a"><?php esc_html_e('Het advies is vrijblijvend en kosteloos.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-faq__item">
                    <h3 class="bressol-faq__q"><?php esc_html_e('Moet ik precies weten wat ik zoek?', 'bressol-theme'); ?></h3>
                    <p class="bressol-faq__a"><?php esc_html_e('Nee, het advies helpt juist om smaak en gelegenheid te vertalen naar een selectie.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-faq__item">
                    <h3 class="bressol-faq__q"><?php esc_html_e('Kan ik advies krijgen voor een cadeau?', 'bressol-theme'); ?></h3>
                    <p class="bressol-faq__a"><?php esc_html_e('Ja, we houden rekening met sfeer, stijl en het moment.', 'bressol-theme'); ?></p>
                </article>
                <article class="bressol-faq__item">
                    <h3 class="bressol-faq__q"><?php esc_html_e('Wat als ik meerdere momenten heb?', 'bressol-theme'); ?></h3>
                    <p class="bressol-faq__a"><?php esc_html_e('Je kunt het advies opnieuw invullen voor een andere selectie.', 'bressol-theme'); ?></p>
                </article>
            </div>
        </div>
    </section>

    <section class="bressol-section" data-track-section="advies-cta">
        <div class="bressol-container">
            <div class="bressol-cta-panel">
                <div>
                    <h2 class="bressol-section-title"><?php esc_html_e('Liever zelf kiezen?', 'bressol-theme'); ?></h2>
                    <p class="bressol-lead"><?php esc_html_e('Bekijk alle pakketten of start alsnog met advies.', 'bressol-theme'); ?></p>
                </div>
                <div>
                    <a class="bressol-button" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                    <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>" data-track-cta="pakketten">
                        <?php esc_html_e('Bekijk pakketten', 'bressol-theme'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
