<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = $args['data'] ?? [];
?>

<section class="bressol-section bressol-origin">
    <div class="bressol-container">
        <div class="bressol-section__header">
            <h2 class="bressol-section__title">Onze oorsprong</h2>
            <p class="bressol-section__lead">Kleine makers, sobere selectie.</p>
        </div>
        <div class="bressol-origin__grid">
            <?php if (is_array($items) && $items !== []) : ?>
                <?php foreach ($items as $item) : ?>
                    <article class="bressol-origin-card">
                        <h3 class="bressol-origin-card__title"><?php echo esc_html($item['title'] ?? 'Origin'); ?></h3>
                        <p class="bressol-origin-card__copy"><?php echo esc_html($item['copy'] ?? ''); ?></p>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <article class="bressol-origin-card">
                    <h3 class="bressol-origin-card__title">Ambacht</h3>
                    <p class="bressol-origin-card__copy">Rustige selectie.</p>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>
