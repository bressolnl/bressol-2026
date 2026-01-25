<?php
declare(strict_types=1);

namespace Bressol\Modules\Seo;

use Bressol\Core\ModuleInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class SeoMetaModule implements ModuleInterface
{
    public function register(): void
    {
        add_action('init', [$this, 'registerMeta']);

        if (is_admin()) {
            (new \Bressol\Modules\Seo\Admin\ProductSeoMetaBox())->register();
        }

        if (class_exists('\WooCommerce')) {
            add_action('woocommerce_single_product_summary', [$this, 'renderIntro'], 35);
            // Details after tabs (prio 10) and before custom recommendations (prio 12).
            add_action('woocommerce_after_single_product_summary', [$this, 'renderDetails'], 11);
        }
    }

    public function registerMeta(): void
    {
        $common = [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ];

        register_post_meta('product', 'bressol_pdp_intro', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeHtml'],
            // Only allow users who can edit this product.
            'auth_callback' => [$this, 'authPostMeta'],
        ]));
        register_post_meta('product', 'bressol_pdp_longform', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeHtml'],
            // Only allow users who can edit this product.
            'auth_callback' => [$this, 'authPostMeta'],
        ]));
        register_post_meta('product', 'bressol_pdp_faq', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeJson'],
            // Only allow users who can edit this product.
            'auth_callback' => [$this, 'authPostMeta'],
        ]));
        register_post_meta('product', 'bressol_origin_ref', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeHtml'],
            // Only allow users who can edit this product.
            'auth_callback' => [$this, 'authPostMeta'],
        ]));
        register_post_meta('product', 'bressol_pack_recommended_moments', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeCsv'],
            // Only allow users who can edit this product.
            'auth_callback' => [$this, 'authPostMeta'],
        ]));
        register_post_meta('product', 'bressol_pack_cross_sell_categories', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeCsv'],
            // Only allow users who can edit this product.
            'auth_callback' => [$this, 'authPostMeta'],
        ]));
    }

    public function renderIntro(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $productId = (int) $product->get_id();
        $intro = (string) get_post_meta($productId, 'bressol_pdp_intro', true);
        if ($intro === '') {
            return;
        }
        ?>
        <section class="bressol-section">
            <div class="bressol-container">
                <div class="bressol-lead">
                    <?php echo wp_kses_post(wpautop($intro)); ?>
                </div>
            </div>
        </section>
        <?php
    }

    public function renderDetails(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $productId = (int) $product->get_id();
        $longform = (string) get_post_meta($productId, 'bressol_pdp_longform', true);
        $origin = (string) get_post_meta($productId, 'bressol_origin_ref', true);
        $faqRaw = (string) get_post_meta($productId, 'bressol_pdp_faq', true);

        $isPack = (string) get_post_meta($productId, '_bressol_pack_definition', true) !== '';
        $momentsCsv = (string) get_post_meta($productId, 'bressol_pack_recommended_moments', true);
        $categoriesCsv = (string) get_post_meta($productId, 'bressol_pack_cross_sell_categories', true);
        $momentSlugs = $this->parseCsv($momentsCsv);
        $categorySlugs = $this->parseCsv($categoriesCsv);

        $hasContent = ($longform !== '') || ($origin !== '');
        $hasPackLinks = $isPack && (!empty($momentSlugs) || !empty($categorySlugs));
        $faqItems = $this->parseFaq($faqRaw);

        if (!$hasContent && !$hasPackLinks && empty($faqItems)) {
            return;
        }
        ?>
        <?php if ($hasContent) : ?>
            <section class="bressol-section">
                <div class="bressol-container">
                    <?php if ($longform !== '') : ?>
                        <div class="bressol-lead">
                            <?php echo wp_kses_post(wpautop($longform)); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($origin !== '') : ?>
                        <div class="bressol-card">
                            <?php echo wp_kses_post(wpautop($origin)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($hasPackLinks) : ?>
            <section class="bressol-section">
                <div class="bressol-container">
                    <?php if (!empty($momentSlugs)) : ?>
                        <h2 class="bressol-section-title">
                            <?php esc_html_e('Aanbevolen momenten', 'bressol-core'); ?>
                        </h2>
                        <ul>
                            <?php foreach ($momentSlugs as $slug) : ?>
                                <?php
                                $term = get_term_by('slug', $slug, 'bressol_moment');
                                $termLink = $term && !is_wp_error($term) ? get_term_link($term) : '';
                                ?>
                                <?php if ($termLink) : ?>
                                    <li><a class="bressol-link" href="<?php echo esc_url($termLink); ?>"><?php echo esc_html($term->name); ?></a></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($categorySlugs)) : ?>
                        <h2 class="bressol-section-title">
                            <?php esc_html_e('Aanbevolen categorieën', 'bressol-core'); ?>
                        </h2>
                        <ul>
                            <?php foreach ($categorySlugs as $slug) : ?>
                                <?php
                                $term = get_term_by('slug', $slug, 'product_cat');
                                $termLink = $term && !is_wp_error($term) ? get_term_link($term) : '';
                                ?>
                                <?php if ($termLink) : ?>
                                    <li><a class="bressol-link" href="<?php echo esc_url($termLink); ?>"><?php echo esc_html($term->name); ?></a></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($faqItems)) : ?>
            <section class="bressol-section">
                <div class="bressol-container">
                    <h2 class="bressol-section-title"><?php esc_html_e('Veelgestelde vragen', 'bressol-core'); ?></h2>
                    <div class="bressol-faq">
                        <?php foreach ($faqItems as $item) : ?>
                            <?php $q = (string) ($item['q'] ?? ''); ?>
                            <?php $a = (string) ($item['a'] ?? ''); ?>
                            <?php if ($q === '' && $a === '') continue; ?>
                            <article class="bressol-faq__item">
                                <?php if ($q !== '') : ?>
                                    <h3 class="bressol-faq__q"><?php echo esc_html($q); ?></h3>
                                <?php endif; ?>
                                <?php if ($a !== '') : ?>
                                    <div class="bressol-faq__a"><?php echo wp_kses_post(wpautop($a)); ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
        <?php
    }

    private function sanitizeHtml($value): string
    {
        return wp_kses_post((string) $value);
    }

    private function authPostMeta($allowed = false, $metaKey = '', $postId = 0, $userId = null, $cap = null, $args = null): bool
    {
        return current_user_can('edit_post', (int) $postId);
    }

    private function sanitizeJson($value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }
        return wp_json_encode($decoded);
    }

    private function sanitizeCsv($value): string
    {
        return self::normalizeCsv($value);
    }

    private function parseCsv(string $csv): array
    {
        if ($csv === '') {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', strtolower($csv))));
        return array_values(array_unique($parts));
    }

    private function parseFaq(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function normalizeCsv($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            $value = implode(',', $value);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $parts = array_map(static function (string $part): string {
            return sanitize_title($part);
        }, $parts);
        $parts = array_values(array_unique(array_filter($parts)));

        return $parts ? implode(',', $parts) : '';
    }
}
