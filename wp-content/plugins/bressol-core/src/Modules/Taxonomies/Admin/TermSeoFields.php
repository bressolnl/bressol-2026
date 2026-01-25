<?php
declare(strict_types=1);

namespace Bressol\Modules\Taxonomies\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class TermSeoFields
{
    public function register(): void
    {
        add_action('init', [$this, 'registerHooks']);
    }

    public function registerHooks(): void
    {
        if (taxonomy_exists('product_cat')) {
            add_action('product_cat_add_form_fields', [$this, 'renderAddProductCat']);
            add_action('product_cat_edit_form_fields', [$this, 'renderEditProductCat']);
            add_action('created_product_cat', [$this, 'saveProductCat']);
            add_action('edited_product_cat', [$this, 'saveProductCat']);
        }

        if (taxonomy_exists('bressol_moment')) {
            add_action('bressol_moment_add_form_fields', [$this, 'renderAddMoment']);
            add_action('bressol_moment_edit_form_fields', [$this, 'renderEditMoment']);
            add_action('created_bressol_moment', [$this, 'saveMoment']);
            add_action('edited_bressol_moment', [$this, 'saveMoment']);
        }
    }

    public function renderAddProductCat(): void
    {
        $this->renderAddFields('bressol_cat');
    }

    public function renderEditProductCat(\WP_Term $term): void
    {
        $this->renderEditFields($term, 'bressol_cat');
    }

    public function saveProductCat(int $termId): void
    {
        $this->saveTermMeta($termId, 'bressol_cat');
    }

    public function renderAddMoment(): void
    {
        $this->renderAddFields('bressol_moment');
    }

    public function renderEditMoment(\WP_Term $term): void
    {
        $this->renderEditFields($term, 'bressol_moment');
    }

    public function saveMoment(int $termId): void
    {
        $this->saveTermMeta($termId, 'bressol_moment');
    }

    private function renderAddFields(string $prefix): void
    {
        wp_nonce_field('bressol_term_seo_save', 'bressol_term_seo_nonce');

        echo '<div class="form-field">';
        echo '<label for="' . esc_attr($prefix . '_intro') . '">Intro</label>';
        echo '<textarea name="' . esc_attr($prefix . '_intro') . '" id="' . esc_attr($prefix . '_intro') . '" rows="4"></textarea>';
        echo '</div>';

        echo '<div class="form-field">';
        echo '<label for="' . esc_attr($prefix . '_longform') . '">Longform</label>';
        echo '<textarea name="' . esc_attr($prefix . '_longform') . '" id="' . esc_attr($prefix . '_longform') . '" rows="8"></textarea>';
        echo '</div>';

        echo '<div class="form-field">';
        echo '<label for="' . esc_attr($prefix . '_faq') . '">FAQ (JSON)</label>';
        echo '<textarea name="' . esc_attr($prefix . '_faq') . '" id="' . esc_attr($prefix . '_faq') . '" rows="6"></textarea>';
        echo '<p><small>Voorbeeld JSON: [{"q":"...","a":"..."},{"q":"...","a":"..."}]</small></p>';
        echo '</div>';
    }

    private function renderEditFields(\WP_Term $term, string $prefix): void
    {
        $intro = (string) get_term_meta($term->term_id, $prefix . '_intro', true);
        $longform = (string) get_term_meta($term->term_id, $prefix . '_longform', true);
        $faq = (string) get_term_meta($term->term_id, $prefix . '_faq', true);

        wp_nonce_field('bressol_term_seo_save', 'bressol_term_seo_nonce');
        ?>
        <tr class="form-field">
            <th scope="row"><label for="<?php echo esc_attr($prefix . '_intro'); ?>">Intro</label></th>
            <td><textarea name="<?php echo esc_attr($prefix . '_intro'); ?>" id="<?php echo esc_attr($prefix . '_intro'); ?>" rows="4"><?php echo esc_textarea($intro); ?></textarea></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="<?php echo esc_attr($prefix . '_longform'); ?>">Longform</label></th>
            <td><textarea name="<?php echo esc_attr($prefix . '_longform'); ?>" id="<?php echo esc_attr($prefix . '_longform'); ?>" rows="8"><?php echo esc_textarea($longform); ?></textarea></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="<?php echo esc_attr($prefix . '_faq'); ?>">FAQ (JSON)</label></th>
            <td>
                <textarea name="<?php echo esc_attr($prefix . '_faq'); ?>" id="<?php echo esc_attr($prefix . '_faq'); ?>" rows="6"><?php echo esc_textarea($faq); ?></textarea>
                <p><small>Voorbeeld JSON: [{"q":"...","a":"..."},{"q":"...","a":"..."}]</small></p>
            </td>
        </tr>
        <?php
    }

    private function saveTermMeta(int $termId, string $prefix): void
    {
        if (!isset($_POST['bressol_term_seo_nonce']) ||
            !wp_verify_nonce((string) $_POST['bressol_term_seo_nonce'], 'bressol_term_seo_save')) {
            return;
        }

        if (!current_user_can('edit_term', $termId)) {
            return;
        }

        $intro = isset($_POST[$prefix . '_intro']) ? wp_unslash($_POST[$prefix . '_intro']) : '';
        $longform = isset($_POST[$prefix . '_longform']) ? wp_unslash($_POST[$prefix . '_longform']) : '';
        $faq = isset($_POST[$prefix . '_faq']) ? wp_unslash($_POST[$prefix . '_faq']) : '';

        update_term_meta($termId, $prefix . '_intro', wp_kses_post((string) $intro));
        update_term_meta($termId, $prefix . '_longform', wp_kses_post((string) $longform));
        update_term_meta($termId, $prefix . '_faq', $this->sanitizeJson((string) $faq));
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
}
