<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

do_action('woocommerce_before_main_content');
?>

<main id="main" class="bressol-main">
    <?php while (have_posts()) : ?>
        <?php the_post(); ?>

        <?php
        $product_id = get_the_ID();
        $product = wc_get_product($product_id);
        $ingredients_full = (string) get_post_meta($product_id, 'bressol_ingredients_full', true);
        $allergens = get_post_meta($product_id, 'bressol_allergens', true);
        $may_contain = get_post_meta($product_id, 'bressol_may_contain', true);
        if (!is_array($allergens)) {
            $allergens = [];
        }
        if (!is_array($may_contain)) {
            $may_contain = [];
        }
        $allergen_options = [
            'gluten_cereals' => __('Glutenbevattende granen', 'bressol-theme'),
            'crustaceans' => __('Schaaldieren', 'bressol-theme'),
            'eggs' => __('Eieren', 'bressol-theme'),
            'fish' => __('Vis', 'bressol-theme'),
            'peanuts' => __('Pinda’s', 'bressol-theme'),
            'soybeans' => __('Soja', 'bressol-theme'),
            'milk' => __('Melk', 'bressol-theme'),
            'nuts' => __('Noten', 'bressol-theme'),
            'celery' => __('Selderij', 'bressol-theme'),
            'mustard' => __('Mosterd', 'bressol-theme'),
            'sesame' => __('Sesam', 'bressol-theme'),
            'sulphites' => __('Zwaveldioxide en sulfieten', 'bressol-theme'),
            'lupin' => __('Lupine', 'bressol-theme'),
            'molluscs' => __('Weekdieren', 'bressol-theme'),
        ];
        $allergen_labels = array_values(array_intersect_key($allergen_options, array_flip($allergens)));
        $may_contain_labels = array_values(array_intersect_key($allergen_options, array_flip($may_contain)));

        $pack_json = (string) get_post_meta($product_id, '_bressol_pack_definition', true);
        $pack_def = $pack_json !== '' ? json_decode($pack_json, true) : null;
        $is_pack = is_array($pack_def) && !empty($pack_def['slots']) && is_array($pack_def['slots']);
        $is_personalizable = false;
        $pack_contents = [];
        if ($is_pack) {
            foreach ($pack_def['slots'] as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                $slot_label = isset($slot['label']) ? (string) $slot['label'] : (string) ($slot['key'] ?? '');
                $slot_label = trim($slot_label);
                if ($slot_label === '') {
                    continue;
                }
                $base_label = preg_replace('/[\s-]*\d+$/', '', $slot_label);
                $group_key = isset($slot['group_key']) ? (string) $slot['group_key'] : '';
                $group_key = $group_key !== '' ? $group_key : $base_label;
                $max = (int) ($slot['max'] ?? 1);
                $count = $max > 1 ? $max : 1;
                if (!isset($pack_contents[$group_key])) {
                    $pack_contents[$group_key] = [
                        'label' => $base_label,
                        'count' => 0,
                    ];
                }
                $pack_contents[$group_key]['count'] += $count;
                $options = isset($slot['options']) && is_array($slot['options']) ? $slot['options'] : [];
                if ($max > 1 || count($options) > 1) {
                    $is_personalizable = true;
                }
            }
        }

        $occasion_value = sanitize_title((string) get_post_meta($product_id, '_bressol_pack_occasion', true));
        $themes_value = array_filter(array_map('trim', explode(',', (string) get_post_meta($product_id, '_bressol_pack_themes', true))));
        $focus_value = array_filter(array_map('trim', explode(',', (string) get_post_meta($product_id, '_bressol_pack_focus', true))));
        $tier_value = sanitize_title((string) get_post_meta($product_id, '_bressol_pack_tier', true));

        $highlight_items = [];
        $label_map = [
            'occasion' => [
                'cadeau' => __('Cadeau-waardig', 'bressol-theme'),
                'borrel' => __('Voor borrel', 'bressol-theme'),
                'tafel' => __('Voor tafel', 'bressol-theme'),
            ],
            'theme' => [
                'borrel' => __('Borrelmoment', 'bressol-theme'),
                'koken' => __('Voor koken', 'bressol-theme'),
            ],
            'focus' => [
                'alcoholvrij' => __('Alcoholvrij', 'bressol-theme'),
            ],
            'tier' => [
                'budget' => __('Toegankelijk', 'bressol-theme'),
                'standard' => __('In balans', 'bressol-theme'),
                'premium' => __('Premium selectie', 'bressol-theme'),
            ],
        ];

        if ($occasion_value !== '' && isset($label_map['occasion'][$occasion_value])) {
            $highlight_items[] = $label_map['occasion'][$occasion_value];
        }

        $theme_first = $themes_value[0] ?? '';
        if ($theme_first !== '' && isset($label_map['theme'][$theme_first])) {
            $highlight_items[] = $label_map['theme'][$theme_first];
        }

        $focus_first = $focus_value[0] ?? '';
        if ($focus_first !== '' && isset($label_map['focus'][$focus_first])) {
            $highlight_items[] = $label_map['focus'][$focus_first];
        }

        if (count($highlight_items) < 3 && $tier_value !== '' && isset($label_map['tier'][$tier_value])) {
            $highlight_items[] = $label_map['tier'][$tier_value];
        }

        $highlight_items = array_values(array_unique($highlight_items));
        $highlight_items = array_slice($highlight_items, 0, 3);

        if ($highlight_items === []) {
            $highlight_items = [
                __('Voor borrel, koken & cadeau', 'bressol-theme'),
                __('Valenciaanse oorsprong', 'bressol-theme'),
                __('Zorgvuldig verpakt', 'bressol-theme'),
            ];
        }

        $advies_link = bressol_get_page_link('advies', '/');
        $pakketten_link = bressol_get_page_link('pakketten', '/pakketten/');

        $short_desc_raw = $product ? $product->get_short_description() : '';
        $short_desc_text = wp_trim_words(wp_strip_all_tags(strip_shortcodes($short_desc_raw)), 28, '...');
        $has_faq = $short_desc_raw !== '' && has_shortcode($short_desc_raw, 'bressol_faq');
        $faq_output = $has_faq ? do_shortcode($short_desc_raw) : '';

        $highlight_allergens = static function (string $text, array $labels): string {
            $html = nl2br(esc_html($text));
            if ($labels === []) {
                return $html;
            }
            foreach ($labels as $label) {
                $escaped = preg_quote(esc_html($label), '/');
                $pattern = '/(?<!\\pL)(' . $escaped . ')(?!\\pL)/iu';
                $html = preg_replace($pattern, '<span class="bressol-allergen">$1</span>', $html);
            }
            return wp_kses($html, [
                'br' => [],
                'span' => ['class' => true],
            ]);
        };

        if (post_password_required()) {
            echo get_the_password_form();
            continue;
        }
        ?>

        <?php do_action('woocommerce_before_single_product'); ?>

        <?php
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        ?>

        <section class="bressol-section bressol-hero bressol-hero--pdp">
            <div class="bressol-container bressol-hero__inner bressol-pdp__hero">
                <div class="bressol-pdp__media">
                    <div class="bressol-pdp__visual" aria-hidden="true"></div>
                    <?php do_action('woocommerce_before_single_product_summary'); ?>
                </div>
                <div class="summary entry-summary bressol-pdp__summary">
                    <h1 class="bressol-title"><?php echo esc_html(get_the_title($product_id)); ?></h1>
                    <?php if ($short_desc_text !== '') : ?>
                        <p class="bressol-pdp__excerpt"><?php echo esc_html($short_desc_text); ?></p>
                    <?php endif; ?>
                    <ul class="bressol-pack-hero__highlights">
                        <?php foreach ($highlight_items as $item) : ?>
                            <li><?php echo esc_html($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="bressol-pdp__price">
                        <?php woocommerce_template_single_price(); ?>
                        <?php echo wp_kses_post($product ? wc_get_stock_html($product) : ''); ?>
                    </div>
                    <div class="bressol-pdp__rating">
                        <?php woocommerce_template_single_rating(); ?>
                    </div>
                    <p class="bressol-pack-hero__trust">
                        <?php esc_html_e('Valenciaanse herkomst · zorgvuldig verpakt', 'bressol-theme'); ?>
                    </p>
                    <a class="bressol-button bressol-button--ghost" href="<?php echo esc_url($advies_link); ?>">
                        <?php esc_html_e('Start met advies', 'bressol-theme'); ?>
                    </a>
                    <p class="bressol-pack-hero__link">
                        <a class="bressol-link" href="<?php echo esc_url($pakketten_link); ?>">
                            <?php esc_html_e('Bekijk alle pakketten', 'bressol-theme'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </section>

        <?php if ($is_pack && $pack_contents !== []) : ?>
            <section class="bressol-section">
                <div class="bressol-container">
                    <h2 class="bressol-section-title"><?php esc_html_e('Wat zit erin', 'bressol-theme'); ?></h2>
                    <div class="bressol-pack-contents">
                        <ul class="bressol-pack-contents__list">
                            <?php foreach ($pack_contents as $item) : ?>
                                <li>
                                    <strong><?php echo esc_html($item['count']); ?>×</strong>
                                    <?php echo esc_html($item['label']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($is_personalizable) : ?>
                            <p class="bressol-pack-contents__note">
                                <span class="bressol-card__badge bressol-card__badge--primary"><?php esc_html_e('Personaliseerbaar', 'bressol-theme'); ?></span>
                                <?php esc_html_e('Kies je selectie hieronder.', 'bressol-theme'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="bressol-section bressol-pack-personalize">
            <div class="bressol-container">
                <h2 class="bressol-section-title">
                    <?php echo $is_pack ? esc_html__('Personaliseer je pakket', 'bressol-theme') : esc_html__('Bestel dit product', 'bressol-theme'); ?>
                </h2>
                <p class="bressol-lead">
                    <?php echo $is_pack ? esc_html__('Kies rustig per onderdeel en voeg daarna toe.', 'bressol-theme') : esc_html__('Voeg toe aan je selectie wanneer het past.', 'bressol-theme'); ?>
                </p>
                <div class="bressol-pack-personalize__body">
                    <?php woocommerce_template_single_add_to_cart(); ?>
                    <?php do_action('woocommerce_single_product_summary'); ?>
                </div>
            </div>
        </section>

        <section class="bressol-section bressol-pack-usecases">
            <div class="bressol-container">
                <h2 class="bressol-section-title"><?php esc_html_e('Voor welk moment', 'bressol-theme'); ?></h2>
                <div class="bressol-grid">
                    <article class="bressol-card">
                        <h3 class="bressol-card__title"><?php esc_html_e('Borrel', 'bressol-theme'); ?></h3>
                        <p class="bressol-card__meta"><?php esc_html_e('Rustige combinaties voor aperitief en sharing.', 'bressol-theme'); ?></p>
                    </article>
                    <article class="bressol-card">
                        <h3 class="bressol-card__title"><?php esc_html_e('Koken', 'bressol-theme'); ?></h3>
                        <p class="bressol-card__meta"><?php esc_html_e('Sober opgebouwd voor tafel en lichte gerechten.', 'bressol-theme'); ?></p>
                    </article>
                    <article class="bressol-card">
                        <h3 class="bressol-card__title"><?php esc_html_e('Cadeau', 'bressol-theme'); ?></h3>
                        <p class="bressol-card__meta"><?php esc_html_e('Een verzorgde selectie om te geven of te delen.', 'bressol-theme'); ?></p>
                    </article>
                </div>
            </div>
        </section>

        <section class="bressol-section">
            <div class="bressol-container">
                <h2 class="bressol-section-title"><?php esc_html_e('Ingrediënten & allergenen', 'bressol-theme'); ?></h2>
                <div class="bressol-prose bressol-compliance">
                    <?php if (trim($ingredients_full) !== '') : ?>
                        <p><?php echo $highlight_allergens($ingredients_full, $allergen_labels); ?></p>
                    <?php else : ?>
                        <p><?php esc_html_e('Ingrediënteninformatie volgt.', 'bressol-theme'); ?></p>
                        <p>
                            <a class="bressol-link" href="<?php echo esc_url(bressol_get_page_link('advies', '/')); ?>">
                                <?php esc_html_e('Vraag advies op maat', 'bressol-theme'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($may_contain_labels)) : ?>
                        <p><strong><?php esc_html_e('Kan sporen bevatten van:', 'bressol-theme'); ?></strong> <?php echo esc_html(implode(', ', $may_contain_labels)); ?>.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($faq_output !== '') : ?>
            <section class="bressol-section">
                <div class="bressol-container">
                    <h2 class="bressol-section-title"><?php esc_html_e('FAQ', 'bressol-theme'); ?></h2>
                    <div class="bressol-prose">
                        <?php echo $faq_output; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="bressol-section bressol-pdp__after">
            <div class="bressol-container">
                <?php do_action('woocommerce_after_single_product_summary'); ?>
            </div>
        </section>

        <?php do_action('woocommerce_after_single_product'); ?>
    <?php endwhile; ?>
</main>

<?php
do_action('woocommerce_after_main_content');

get_footer();
