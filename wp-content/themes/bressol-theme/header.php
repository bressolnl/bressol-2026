<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="bressol-skip-link" href="#main"><?php esc_html_e('Skip to content', 'bressol-theme'); ?></a>
<header class="bressol-header" data-bressol-header>
    <div class="bressol-header__inner">
        <a class="bressol-header__logo" href="<?php echo esc_url(home_url('/')); ?>">
            BRESSOL
        </a>
        <nav class="bressol-header__nav" aria-label="<?php echo esc_attr__('Hoofdnavigatie', 'bressol-theme'); ?>">
            <ul class="bressol-header__nav-list">
                <li><a class="bressol-header__nav-link" href="#">Momenten</a></li>
                <li><a class="bressol-header__nav-link" href="#">Shop</a></li>
                <li><a class="bressol-header__nav-link" href="#">Cadeaus</a></li>
                <li><a class="bressol-header__nav-link" href="#">Oorsprong</a></li>
                <li><a class="bressol-header__nav-link" href="#">Club</a></li>
            </ul>
        </nav>
        <div class="bressol-header__actions" aria-label="<?php echo esc_attr__('Snelle acties', 'bressol-theme'); ?>">
            <a class="bressol-header__icon" href="#" aria-label="<?php echo esc_attr__('Zoeken', 'bressol-theme'); ?>">
                <span class="screen-reader-text"><?php esc_html_e('Zoeken', 'bressol-theme'); ?></span>
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M11 3a8 8 0 1 0 4.9 14.3l4 4a1 1 0 0 0 1.4-1.4l-4-4A8 8 0 0 0 11 3zm0 2a6 6 0 1 1 0 12 6 6 0 0 1 0-12z" fill="currentColor"/>
                </svg>
            </a>
            <a class="bressol-header__icon" href="#" aria-label="<?php echo esc_attr__('Winkelwagen', 'bressol-theme'); ?>">
                <span class="screen-reader-text"><?php esc_html_e('Winkelwagen', 'bressol-theme'); ?></span>
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M6 6h14l-2 9H8L6 6zm2 13a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z" fill="currentColor"/>
                </svg>
                <span class="bressol-header__badge" aria-hidden="true">2</span>
            </a>
            <button class="bressol-header__burger" type="button" data-bressol-hamburger aria-label="<?php echo esc_attr__('Menu', 'bressol-theme'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </div>
</header>

<div class="bressol-drawer__overlay" data-bressol-drawer-close aria-hidden="true"></div>
<aside class="bressol-drawer" data-bressol-drawer aria-hidden="true">
    <div class="bressol-drawer__header">
        <span class="bressol-header__logo">BRESSOL</span>
        <button class="bressol-drawer__close" type="button" data-bressol-drawer-close aria-label="<?php echo esc_attr__('Sluiten', 'bressol-theme'); ?>">
            &times;
        </button>
    </div>
    <nav class="bressol-drawer__nav" aria-label="<?php echo esc_attr__('Mobiele navigatie', 'bressol-theme'); ?>">
        <a class="bressol-drawer__link" href="#">Momenten</a>
        <a class="bressol-drawer__link" href="#">Shop</a>
        <a class="bressol-drawer__link" href="#">Cadeaus</a>
        <a class="bressol-drawer__link" href="#">Oorsprong</a>
        <a class="bressol-drawer__link" href="#">Club</a>
    </nav>
</aside>

<div class="bressol-overlay" aria-hidden="true"></div>

<?php
$shop_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
$shop_url = $shop_page_id ? get_permalink($shop_page_id) : home_url('/');
?>
<aside class="bressol-panel bressol-panel--search" id="bressol-panel-search" data-bressol-panel="search" aria-hidden="true" role="dialog" aria-label="<?php echo esc_attr__('Zoeken', 'bressol-theme'); ?>">
    <div class="bressol-panel__header">
        <h2 class="bressol-panel__title"><?php esc_html_e('Zoeken', 'bressol-theme'); ?></h2>
        <button class="bressol-panel__close" type="button" data-bressol-close aria-label="<?php echo esc_attr__('Sluiten', 'bressol-theme'); ?>">&times;</button>
    </div>
    <form role="search" method="get" class="bressol-search-form" action="<?php echo esc_url($shop_url); ?>">
        <label class="screen-reader-text" for="bressol-search-field">
            <?php esc_html_e('Zoeken naar producten', 'bressol-theme'); ?>
        </label>
        <input
            type="search"
            id="bressol-search-field"
            class="bressol-search-field"
            name="s"
            value="<?php echo esc_attr(get_search_query()); ?>"
            placeholder="<?php esc_attr_e('Zoek producten', 'bressol-theme'); ?>"
            autocomplete="off"
            autofocus
            required
        >
        <input type="hidden" name="post_type" value="product">
        <button type="submit" class="bressol-search-submit">
            <?php esc_html_e('Zoeken', 'bressol-theme'); ?>
        </button>
    </form>
</aside>

<aside class="bressol-panel bressol-panel--cart" id="bressol-panel-cart" data-bressol-panel="cart" aria-hidden="true" role="dialog" aria-label="<?php echo esc_attr__('Winkelwagen', 'bressol-theme'); ?>">
    <div class="bressol-panel__header">
        <h2 class="bressol-panel__title"><?php esc_html_e('Winkelwagen', 'bressol-theme'); ?></h2>
        <button class="bressol-panel__close" type="button" data-bressol-close aria-label="<?php echo esc_attr__('Sluiten', 'bressol-theme'); ?>">&times;</button>
    </div>
    <div class="bressol-mini-cart widget_shopping_cart">
        <div class="widget_shopping_cart_content">
            <?php if (function_exists('woocommerce_mini_cart')) : ?>
                <?php woocommerce_mini_cart(); ?>
            <?php else : ?>
                <p class="woocommerce-mini-cart__empty-message"><?php esc_html_e('Je winkelwagen is leeg.', 'bressol-theme'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</aside>