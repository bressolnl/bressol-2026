<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = $args['data'] ?? [];
?>

<section class="bressol-section bressol-editorials">
    <div class="bressol-container">
        <div class="bressol-section__header">
            <h2 class="bressol-section__title">Editorials</h2>
            <p class="bressol-section__lead">Verhalen over herkomst, selectie en moment.</p>
        </div>
        <div class="bressol-editorials__grid">
            <?php if (is_array($items) && $items !== []) : ?>
                <?php foreach ($items as $item) : ?>
                    <?php $ratio = isset($item['image_ratio']) ? (string) $item['image_ratio'] : '3 / 2'; ?>
                    <article class="bressol-editorial-card">
                        <a class="bressol-editorial-card__media" href="<?php echo esc_url($item['url'] ?? '#'); ?>" style="aspect-ratio: <?php echo esc_attr($ratio); ?>">
                            <span class="bressol-media-placeholder" aria-hidden="true"></span>
                        </a>
                        <h3 class="bressol-editorial-card__title"><?php echo esc_html($item['title'] ?? 'Editorial'); ?></h3>
                        <p class="bressol-editorial-card__copy"><?php echo esc_html($item['copy'] ?? ''); ?></p>
                        <a class="bressol-editorial-card__cta" href="<?php echo esc_url($item['url'] ?? '#'); ?>">Lees meer</a>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <article class="bressol-editorial-card">
                    <div class="bressol-editorial-card__media" style="aspect-ratio: 3 / 2">
                        <span class="bressol-media-placeholder" aria-hidden="true"></span>
                    </div>
                    <h3 class="bressol-editorial-card__title">Bressol editorial</h3>
                    <p class="bressol-editorial-card__copy">Rustig en mediterraan.</p>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>
