<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = $args['data'] ?? [];
?>

<section class="bressol-section bressol-bestsellers">
    <div class="bressol-container">
        <div class="bressol-section__header">
            <h2 class="bressol-section__title">Bestsellers</h2>
            <p class="bressol-section__lead">De meest gekozen selectie, klaar om snel toe te voegen.</p>
        </div>
        <div class="bressol-bestsellers__grid">
            <?php if (is_array($items) && $items !== []) : ?>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $ratio = isset($item['image_ratio']) ? (string) $item['image_ratio'] : '4 / 5';
                    $product_id = isset($item['product_id']) ? (string) $item['product_id'] : '';
                    ?>
                    <article class="bressol-product-card">
                        <div class="bressol-product-card__media" style="aspect-ratio: <?php echo esc_attr($ratio); ?>">
                            <span class="bressol-media-placeholder" aria-hidden="true"></span>
                        </div>
                        <div class="bressol-product-card__body">
                            <h3 class="bressol-product-card__title"><?php echo esc_html($item['title'] ?? 'Product'); ?></h3>
                            <p class="bressol-product-card__subtitle"><?php echo esc_html($item['subtitle'] ?? ''); ?></p>
                            <span class="bressol-product-card__price"><?php echo esc_html($item['price'] ?? ''); ?></span>
                        </div>
                        <button
                            class="bressol-product-card__quick-add"
                            type="button"
                            data-bressol-quick-add
                            data-product-id="<?php echo esc_attr($product_id); ?>"
                            data-qty="1"
                        >
                            Snel toevoegen
                        </button>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <article class="bressol-product-card">
                    <div class="bressol-product-card__media" style="aspect-ratio: 4 / 5">
                        <span class="bressol-media-placeholder" aria-hidden="true"></span>
                    </div>
                    <div class="bressol-product-card__body">
                        <h3 class="bressol-product-card__title">Bressol selectie</h3>
                        <p class="bressol-product-card__subtitle">Rustige start</p>
                        <span class="bressol-product-card__price">€0</span>
                    </div>
                    <button class="bressol-product-card__quick-add" type="button" data-bressol-quick-add data-product-id="0" data-qty="1">
                        Snel toevoegen
                    </button>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>
