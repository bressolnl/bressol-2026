<?php
if (!defined('ABSPATH')) {
    exit;
}

$slides = $args['data'] ?? [];
$total = is_array($slides) ? count($slides) : 0;
?>

<section class="bressol-section bressol-hero" data-bressol-slider>
    <div class="bressol-container bressol-hero__inner">
        <div class="bressol-hero__content">
            <?php if ($total > 0) : ?>
                <?php foreach ($slides as $index => $slide) : ?>
                    <?php
                    $title = isset($slide['title']) ? (string) $slide['title'] : '';
                    $copy = isset($slide['copy']) ? (string) $slide['copy'] : '';
                    $primary = $slide['primary_cta'] ?? [];
                    $secondary = $slide['secondary_cta'] ?? [];
                    $ratio = isset($slide['image_ratio']) ? (string) $slide['image_ratio'] : '16 / 9';
                    ?>
                    <article class="bressol-hero__slide" data-bressol-slide>
                        <h1 class="bressol-hero__title"><?php echo esc_html($title); ?></h1>
                        <p class="bressol-hero__copy"><?php echo esc_html($copy); ?></p>
                        <div class="bressol-hero__cta">
                            <a class="bressol-button" href="<?php echo esc_url($primary['url'] ?? '#'); ?>">
                                <?php echo esc_html($primary['label'] ?? 'Ontdek'); ?>
                            </a>
                            <a class="bressol-button bressol-button--ghost" href="<?php echo esc_url($secondary['url'] ?? '#'); ?>">
                                <?php echo esc_html($secondary['label'] ?? 'Lees meer'); ?>
                            </a>
                        </div>
                        <div class="bressol-hero__media" style="aspect-ratio: <?php echo esc_attr($ratio); ?>">
                            <span class="bressol-media-placeholder" aria-hidden="true"></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <article class="bressol-hero__slide" data-bressol-slide>
                    <h1 class="bressol-hero__title">Bressol v2</h1>
                    <p class="bressol-hero__copy">Rustige selectie, mediterrane stijl.</p>
                    <div class="bressol-hero__cta">
                        <a class="bressol-button" href="#">Ontdek</a>
                        <a class="bressol-button bressol-button--ghost" href="#">Lees meer</a>
                    </div>
                    <div class="bressol-hero__media" style="aspect-ratio: 16 / 9">
                        <span class="bressol-media-placeholder" aria-hidden="true"></span>
                    </div>
                </article>
            <?php endif; ?>
        </div>
        <div class="bressol-hero__controls">
            <div class="bressol-hero__counter" data-bressol-counter>
                <span class="bressol-hero__current">01</span>
                <span class="bressol-hero__divider">/</span>
                <span class="bressol-hero__total"><?php echo esc_html(str_pad((string) max(1, $total), 2, '0', STR_PAD_LEFT)); ?></span>
            </div>
            <div class="bressol-hero__dots" role="tablist" aria-label="Hero slides">
                <?php for ($i = 0; $i < max(1, $total); $i++) : ?>
                    <button class="bressol-hero__dot" type="button" data-bressol-dot aria-label="<?php echo esc_attr('Slide ' . ($i + 1)); ?>"></button>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>
