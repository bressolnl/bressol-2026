<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = $args['data'] ?? [];
?>

<section class="bressol-section bressol-gifts">
    <div class="bressol-container">
        <div class="bressol-section__header">
            <h2 class="bressol-section__title">Gifts & Packs</h2>
            <p class="bressol-section__lead">Samengesteld met rustige elegantie.</p>
        </div>
        <div class="bressol-gifts__grid">
            <?php if (is_array($items) && $items !== []) : ?>
                <?php foreach ($items as $item) : ?>
                    <?php $ratio = isset($item['image_ratio']) ? (string) $item['image_ratio'] : '1 / 1'; ?>
                    <a class="bressol-gift-card" href="<?php echo esc_url($item['url'] ?? '#'); ?>">
                        <div class="bressol-gift-card__media" style="aspect-ratio: <?php echo esc_attr($ratio); ?>">
                            <span class="bressol-media-placeholder" aria-hidden="true"></span>
                        </div>
                        <h3 class="bressol-gift-card__title"><?php echo esc_html($item['title'] ?? 'Gift'); ?></h3>
                        <p class="bressol-gift-card__copy"><?php echo esc_html($item['copy'] ?? ''); ?></p>
                    </a>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="bressol-gift-card">
                    <div class="bressol-gift-card__media" style="aspect-ratio: 1 / 1">
                        <span class="bressol-media-placeholder" aria-hidden="true"></span>
                    </div>
                    <h3 class="bressol-gift-card__title">Gift</h3>
                    <p class="bressol-gift-card__copy">Rustige selectie.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
