<?php
declare(strict_types=1);

namespace Bressol\Modules\Seo\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductSeoMetaBox
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post_product', [$this, 'save']);
    }

    public function addMetaBox(): void
    {
        add_meta_box(
            'bressol_seo_pdp',
            'Bressol SEO (PDP)',
            [$this, 'render'],
            'product',
            'normal',
            'high'
        );
    }

    public function render(\WP_Post $post): void
    {
        $intro = (string) get_post_meta($post->ID, 'bressol_pdp_intro', true);
        $longform = (string) get_post_meta($post->ID, 'bressol_pdp_longform', true);
        $origin = (string) get_post_meta($post->ID, 'bressol_origin_ref', true);
        $faq = (string) get_post_meta($post->ID, 'bressol_pdp_faq', true);
        $packMoments = (string) get_post_meta($post->ID, 'bressol_pack_recommended_moments', true);
        $packCategories = (string) get_post_meta($post->ID, 'bressol_pack_cross_sell_categories', true);

        wp_nonce_field('bressol_seo_pdp_save', 'bressol_seo_pdp_nonce');

        echo '<p><strong>Intro (PDP)</strong></p>';
        echo '<textarea class="large-text" name="bressol_pdp_intro" rows="4">' . esc_textarea($intro) . '</textarea>';

        echo '<p><strong>Longform (PDP)</strong></p>';
        echo '<textarea class="large-text" name="bressol_pdp_longform" rows="8">' . esc_textarea($longform) . '</textarea>';

        echo '<p><strong>Oorsprong</strong></p>';
        echo '<textarea class="large-text" name="bressol_origin_ref" rows="4">' . esc_textarea($origin) . '</textarea>';

        echo '<p><strong>FAQ (JSON)</strong></p>';
        echo '<textarea class="large-text" name="bressol_pdp_faq" rows="6">' . esc_textarea($faq) . '</textarea>';
        echo '<p><small>Voorbeeld JSON: [{"q":"...","a":"..."},{"q":"...","a":"..."}]</small></p>';

        echo '<p><strong>Pack moments (CSV)</strong></p>';
        echo '<textarea class="large-text" name="bressol_pack_recommended_moments" rows="2">' . esc_textarea($packMoments) . '</textarea>';
        echo '<p><small>Voorbeeld: shared_table,cooking,gift</small></p>';

        echo '<p><strong>Pack categorieën (product_cat slugs)</strong></p>';
        echo '<textarea class="large-text" name="bressol_pack_cross_sell_categories" rows="2">' . esc_textarea($packCategories) . '</textarea>';
        echo '<p><small>Voorbeeld: borrel,drinks,smaakmakers</small></p>';
    }

    public function save(int $postId): void
    {
        if (!isset($_POST['bressol_seo_pdp_nonce']) ||
            !wp_verify_nonce((string) $_POST['bressol_seo_pdp_nonce'], 'bressol_seo_pdp_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $intro = isset($_POST['bressol_pdp_intro']) ? wp_unslash($_POST['bressol_pdp_intro']) : '';
        $longform = isset($_POST['bressol_pdp_longform']) ? wp_unslash($_POST['bressol_pdp_longform']) : '';
        $origin = isset($_POST['bressol_origin_ref']) ? wp_unslash($_POST['bressol_origin_ref']) : '';
        $faq = isset($_POST['bressol_pdp_faq']) ? wp_unslash($_POST['bressol_pdp_faq']) : '';
        $packMoments = isset($_POST['bressol_pack_recommended_moments']) ? wp_unslash($_POST['bressol_pack_recommended_moments']) : '';
        $packCategories = isset($_POST['bressol_pack_cross_sell_categories']) ? wp_unslash($_POST['bressol_pack_cross_sell_categories']) : '';

        update_post_meta($postId, 'bressol_pdp_intro', wp_kses_post((string) $intro));
        update_post_meta($postId, 'bressol_pdp_longform', wp_kses_post((string) $longform));
        update_post_meta($postId, 'bressol_origin_ref', wp_kses_post((string) $origin));
        update_post_meta($postId, 'bressol_pdp_faq', $this->sanitizeJson((string) $faq));
        update_post_meta($postId, 'bressol_pack_recommended_moments', $this->sanitizeCsv((string) $packMoments));
        update_post_meta($postId, 'bressol_pack_cross_sell_categories', $this->sanitizeCsv((string) $packCategories));
    }

    private function sanitizeJson(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }
        return wp_json_encode($decoded);
    }

    private function sanitizeCsv(string $value): string
    {
        return \Bressol\Modules\Seo\SeoMetaModule::normalizeCsv($value);
    }
}
