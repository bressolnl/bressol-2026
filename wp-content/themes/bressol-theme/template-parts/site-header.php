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
<header class="bressol-site-header">
    <div class="bressol-site-header__inner bressol-container">
        <div class="bressol-site-header__brand">
            <a class="bressol-brand" href="<?php echo esc_url(home_url('/')); ?>">
                <span class="bressol-brand__name">Bressol</span>
            </a>
        </div>
        <nav class="bressol-nav" aria-label="<?php echo esc_attr__('Hoofdnavigatie', 'bressol-theme'); ?>">
            <?php if (has_nav_menu('primary')) : ?>
                <?php wp_nav_menu([
                    'theme_location' => 'primary',
                    'container' => false,
                    'menu_class' => 'bressol-nav__list',
                    'depth' => 1,
                    'fallback_cb' => false,
                ]); ?>
            <?php else : ?>
                <ul class="bressol-nav__list">
                    <li class="bressol-nav__item">
                        <a class="bressol-nav__link" href="<?php echo esc_url(home_url('/pakketten/')); ?>">
                            <?php esc_html_e('Pakketten', 'bressol-theme'); ?>
                        </a>
                    </li>
                    <li class="bressol-nav__item">
                        <a class="bressol-nav__link" href="<?php echo esc_url(home_url('/advies/')); ?>">
                            <?php esc_html_e('Advies', 'bressol-theme'); ?>
                        </a>
                    </li>
                    <li class="bressol-nav__item">
                        <a class="bressol-nav__link" href="<?php echo esc_url(home_url('/moment/')); ?>">
                            <?php esc_html_e('Momenten', 'bressol-theme'); ?>
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </nav>
        <div class="bressol-site-header__actions" aria-label="<?php echo esc_attr__('Snelle acties', 'bressol-theme'); ?>">
            <button class="bressol-site-header__icon" type="button" data-bressol-toggle="search" aria-expanded="false" aria-controls="bressol-panel-search">
                <span class="screen-reader-text"><?php esc_html_e('Zoeken', 'bressol-theme'); ?></span>
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M11 3a8 8 0 1 0 4.9 14.3l4 4a1 1 0 0 0 1.4-1.4l-4-4A8 8 0 0 0 11 3zm0 2a6 6 0 1 1 0 12 6 6 0 0 1 0-12z" fill="currentColor"/>
                </svg>
            </button>
            <button class="bressol-site-header__icon" type="button" data-bressol-toggle="account" aria-expanded="false" aria-controls="bressol-panel-account">
                <span class="screen-reader-text"><?php esc_html_e('Account', 'bressol-theme'); ?></span>
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M12 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm0 10c3.9 0 7 2 7 4.5V21H5v-2.5C5 16 8.1 14 12 14z" fill="currentColor"/>
                </svg>
            </button>
            <button class="bressol-site-header__icon" type="button" data-bressol-toggle="cart" aria-expanded="false" aria-controls="bressol-panel-cart">
                <span class="screen-reader-text"><?php esc_html_e('Winkelwagen', 'bressol-theme'); ?></span>
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M6 6h14l-2 9H8L6 6zm2 13a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z" fill="currentColor"/>
                </svg>
            </button>
        </div>
    </div>
</header>

<div class="bressol-overlay" aria-hidden="true"></div>

<aside class="bressol-panel" id="bressol-panel-search" data-bressol-panel="search" aria-hidden="true" role="dialog">
    <div class="bressol-panel__header">
        <h2 class="bressol-section__title"><?php esc_html_e('Zoeken', 'bressol-theme'); ?></h2>
        <button class="bressol-panel__close" type="button" data-bressol-close aria-label="<?php echo esc_attr__('Sluiten', 'bressol-theme'); ?>">&times;</button>
    </div>
    <?php get_search_form(); ?>
</aside>

<aside class="bressol-panel" id="bressol-panel-account" data-bressol-panel="account" aria-hidden="true" role="dialog">
    <div class="bressol-panel__header">
        <h2 class="bressol-section__title"><?php esc_html_e('Account', 'bressol-theme'); ?></h2>
        <button class="bressol-panel__close" type="button" data-bressol-close aria-label="<?php echo esc_attr__('Sluiten', 'bressol-theme'); ?>">&times;</button>
    </div>
    <p><?php esc_html_e('Log in of maak een account aan om je bestellingen te volgen.', 'bressol-theme'); ?></p>
    <?php if (function_exists('wc_get_page_id')) : ?>
        <a class="bressol-cta" href="<?php echo esc_url(get_permalink(wc_get_page_id('myaccount'))); ?>">
            <?php esc_html_e('Ga naar mijn account', 'bressol-theme'); ?>
        </a>
    <?php endif; ?>
</aside>

<aside class="bressol-panel" id="bressol-panel-cart" data-bressol-panel="cart" aria-hidden="true" role="dialog">
    <div class="bressol-panel__header">
        <h2 class="bressol-section__title"><?php esc_html_e('Winkelwagen', 'bressol-theme'); ?></h2>
        <button class="bressol-panel__close" type="button" data-bressol-close aria-label="<?php echo esc_attr__('Sluiten', 'bressol-theme'); ?>">&times;</button>
    </div>
    <?php if (function_exists('woocommerce_mini_cart')) : ?>
        <?php woocommerce_mini_cart(); ?>
    <?php else : ?>
        <p><?php esc_html_e('Je winkelwagen is leeg.', 'bressol-theme'); ?></p>
    <?php endif; ?>
</aside>
