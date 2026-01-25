<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="bressol-main">
    <section class="bressol-section">
        <div class="bressol-container">
            <?php if (have_posts()) : ?>
                <?php while (have_posts()) : ?>
                    <?php the_post(); ?>
                    <h1 class="bressol-title"><?php the_title(); ?></h1>
                    <div class="bressol-content">
                        <?php the_content(); ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
get_footer();
