<?php
if (!defined('ABSPATH')) {
    exit;
}

$items = $args['data'] ?? [];
?>

<section class="bressol-section bressol-trust">
    <div class="bressol-container">
        <ul class="bressol-trust__list">
            <?php if (is_array($items) && $items !== []) : ?>
                <?php foreach ($items as $item) : ?>
                    <li class="bressol-trust__item"><?php echo esc_html((string) $item); ?></li>
                <?php endforeach; ?>
            <?php else : ?>
                <li class="bressol-trust__item">Herkomst met karakter</li>
                <li class="bressol-trust__item">Ambachtelijk gekozen</li>
                <li class="bressol-trust__item">Evenwichtige smaak</li>
            <?php endif; ?>
        </ul>
    </div>
</section>
