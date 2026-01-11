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
<header style="padding:16px; border-bottom:1px solid #ddd;">
    <a href="<?php echo esc_url(home_url('/')); ?>" style="text-decoration:none;">
        <strong>Bressol</strong>
    </a>
</header>