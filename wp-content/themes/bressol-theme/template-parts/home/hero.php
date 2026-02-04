<?php
if (!defined('ABSPATH')) {
    exit;
}

$slides = $args['data'] ?? [];
$total = is_array($slides) ? count($slides) : 0;
?>

<section class="bressol-section bressol-hero" data-bressol-slider tabindex="0" aria-roledescription="carousel">
    <div class="bressol-hero__inner">
        <div class="bressol-hero__slides">
            <?php if ($total > 0) : ?>
                <?php foreach ($slides as $index => $slide) : ?>
                <?php
                $secondary = $slide['secondary_cta'] ?? [];
                $desktop_image = isset($slide['desktop_image']) ? (string) $slide['desktop_image'] : '';
                $mobile_image = isset($slide['mobile_image']) ? (string) $slide['mobile_image'] : '';
                $base_uri = get_stylesheet_directory_uri();
                $desktop_src = $desktop_image !== '' ? $base_uri . $desktop_image : '';
                $mobile_src = $mobile_image !== '' ? $base_uri . $mobile_image : $desktop_src;
                $slide_class = 'bressol-hero__slide bressol-hero__slide--' . ($index + 1);
                if ($index === 0) {
                    $slide_class .= ' bressol-is-active';
                }
                ?>
                <article
                    class="<?php echo esc_attr($slide_class); ?>"
                    data-bressol-slide
                    style="--bressol-hero-image: url('<?php echo esc_url($desktop_src); ?>'); --bressol-hero-image-mobile: url('<?php echo esc_url($mobile_src); ?>');"
                >
                    <picture class="bressol-hero__image" aria-hidden="true">
                        <?php if ($desktop_src !== '') : ?>
                            <source media="(min-width: 900px)" srcset="<?php echo esc_url($desktop_src); ?>">
                        <?php endif; ?>
                        <?php if ($mobile_src !== '') : ?>
                            <img src="<?php echo esc_url($mobile_src); ?>" alt="" decoding="async">
                        <?php endif; ?>
                    </picture>
                        <div class="bressol-container bressol-hero__content">
                            <span class="bressol-hero__micro">A TAULA</span>
                            <h1 class="bressol-hero__title">Jouw moment, op z&rsquo;n Valenciaans.</h1>
                            <p class="bressol-hero__copy">Een tafel vol kleine hapjes. Een glas. Even samen.</p>
                            <div class="bressol-hero__cta">
                                <a class="bressol-button" href="#momenten">Ontdek momenten</a>
                                <a class="bressol-button bressol-button--ghost" href="<?php echo esc_url($secondary['url'] ?? '#'); ?>">
                                    <?php echo esc_html($secondary['label'] ?? 'Shop'); ?>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <article
                    class="bressol-hero__slide bressol-hero__slide--1 bressol-is-active"
                    data-bressol-slide
                    style="--bressol-hero-image: url('<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/hero/hero-borrel-desktop.svg'); ?>'); --bressol-hero-image-mobile: url('<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/hero/hero-borrel-mobile.svg'); ?>');"
                >
                    <picture class="bressol-hero__image" aria-hidden="true">
                        <source media="(min-width: 900px)" srcset="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/hero/hero-borrel-desktop.svg'); ?>">
                        <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/hero/hero-borrel-mobile.svg'); ?>" alt="" decoding="async">
                    </picture>
                    <div class="bressol-container bressol-hero__content">
                        <span class="bressol-hero__micro">A TAULA</span>
                        <h1 class="bressol-hero__title">Jouw moment, op z&rsquo;n Valenciaans.</h1>
                        <p class="bressol-hero__copy">Een tafel vol kleine hapjes. Een glas. Even samen.</p>
                        <div class="bressol-hero__cta">
                            <a class="bressol-button" href="#momenten">Ontdek momenten</a>
                            <a class="bressol-button bressol-button--ghost" href="#">Shop borrel &amp; dranken</a>
                        </div>
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
            <div class="bressol-hero__nav">
                <button class="bressol-hero__nav-btn" type="button" data-bressol-prev aria-label="Vorige slide">&larr;</button>
                <button class="bressol-hero__nav-btn" type="button" data-bressol-next aria-label="Volgende slide">&rarr;</button>
            </div>
        </div>
    </div>
</section>
