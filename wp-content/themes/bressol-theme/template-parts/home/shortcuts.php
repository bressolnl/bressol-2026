<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = $args['data'] ?? [];
?>

<section class="bressol-section bressol-shortcuts">
    <div class="bressol-container">
        <div class="bressol-section__header">
            <h2 class="bressol-section__title">Snelle routes</h2>
            <p class="bressol-section__lead">Kies een startpunt dat past bij je moment.</p>
        </div>
        <div class="bressol-shortcuts__grid">
            <?php if (is_array($items) && $items !== []) : ?>
                <?php foreach ($items as $item) : ?>
                    <a class="bressol-shortcut-card" href="<?php echo esc_url($item['url'] ?? '#'); ?>">
                        <h3 class="bressol-shortcut-card__title"><?php echo esc_html($item['title'] ?? 'Shortcut'); ?></h3>
                        <p class="bressol-shortcut-card__copy"><?php echo esc_html($item['copy'] ?? ''); ?></p>
                        <span class="bressol-shortcut-card__cta">Bekijk</span>
                    </a>
                <?php endforeach; ?>
            <?php else : ?>
                <a class="bressol-shortcut-card" href="#">
                    <h3 class="bressol-shortcut-card__title">Advies</h3>
                    <p class="bressol-shortcut-card__copy">Start met een rustige selectie.</p>
                    <span class="bressol-shortcut-card__cta">Bekijk</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
