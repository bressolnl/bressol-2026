<?php
if (!defined('ABSPATH')) {
    exit;
}

$data = $args['data'] ?? [];
$title = isset($data['title']) ? (string) $data['title'] : 'Bressol club';
$copy = isset($data['copy']) ? (string) $data['copy'] : 'Ontvang rustige updates.';
$cta_label = isset($data['cta_label']) ? (string) $data['cta_label'] : 'Inschrijven';
$privacy = isset($data['privacy']) ? (string) $data['privacy'] : 'Geen spam. Uitschrijven kan altijd.';
?>

<section class="bressol-section bressol-newsletter">
    <div class="bressol-container">
        <div class="bressol-newsletter__content">
            <h2 class="bressol-section__title"><?php echo esc_html($title); ?></h2>
            <p class="bressol-section__lead"><?php echo esc_html($copy); ?></p>
        </div>
        <form class="bressol-newsletter__form" action="#" method="post">
            <label class="bressol-newsletter__label" for="bressol-newsletter-email">E-mailadres</label>
            <div class="bressol-newsletter__fields">
                <input
                    class="bressol-newsletter__input"
                    type="email"
                    id="bressol-newsletter-email"
                    name="email"
                    placeholder="you@bressol.com"
                    required
                >
                <button class="bressol-button" type="submit"><?php echo esc_html($cta_label); ?></button>
            </div>
            <p class="bressol-newsletter__privacy"><?php echo esc_html($privacy); ?></p>
        </form>
    </div>
</section>
