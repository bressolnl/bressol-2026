<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = $args['data'] ?? [];
?>

<section class="bressol-section bressol-moments">
    <div class="bressol-container">
        <div class="bressol-section__header">
            <h2 class="bressol-section__title">Voor elk moment</h2>
            <p class="bressol-section__lead">Kies een context en ontdek wat past bij de tafel.</p>
        </div>
        <div class="bressol-moments__grid">
            <?php if (is_array($items) && $items !== []) : ?>
                <?php foreach ($items as $item) : ?>
                    <a class="bressol-moment-card" href="<?php echo esc_url($item['url'] ?? '#'); ?>">
                        <h3 class="bressol-moment-card__title"><?php echo esc_html($item['title'] ?? 'Moment'); ?></h3>
                        <p class="bressol-moment-card__copy"><?php echo esc_html($item['copy'] ?? ''); ?></p>
                    </a>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="bressol-moment-card">
                    <h3 class="bressol-moment-card__title">Aperitief</h3>
                    <p class="bressol-moment-card__copy">Rustige accenten.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
