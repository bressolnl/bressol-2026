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
        $trustItems = $this->getMetaLines($post->ID, 'bressol_pdp_trust_', 4);
        $useItems = $this->getMetaLines($post->ID, 'bressol_pdp_use_', 3);
        $pairItems = $this->getMetaLines($post->ID, 'bressol_pdp_pair_', 3);
        $originText = (string) get_post_meta($post->ID, 'bressol_pdp_origin_text', true);
        $storageText = (string) get_post_meta($post->ID, 'bressol_pdp_storage_text', true);
        $textureItems = $this->getMetaLines($post->ID, 'bressol_smaak_textuur', 3);
        $ingredientItems = $this->getMetaLines($post->ID, 'bressol_smaak_ingredienten', 3);
        $characterItems = $this->getMetaLines($post->ID, 'bressol_smaak_karakter', 3);
        $ingredientsFull = (string) get_post_meta($post->ID, 'bressol_ingredients_full', true);
        $allergens = $this->getMetaArray($post->ID, 'bressol_allergens');
        $mayContain = $this->getMetaArray($post->ID, 'bressol_may_contain');
        $allergenOptions = $this->getAllergenOptions();

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

        echo '<hr style="margin:16px 0;">';
        echo '<h3>Bressol PDP (editorial)</h3>';

        echo '<p><strong>Trust bar (4 items)</strong></p>';
        for ($i = 1; $i <= 4; $i++) {
            $value = $trustItems[$i - 1] ?? '';
            echo '<input class="widefat" type="text" maxlength="120" name="bressol_pdp_trust_' . $i . '" value="' . esc_attr($value) . '">';
        }

        echo '<p><strong>Smaak & gebruik (3 bullets)</strong></p>';
        for ($i = 1; $i <= 3; $i++) {
            $value = $useItems[$i - 1] ?? '';
            echo '<input class="widefat" type="text" maxlength="120" name="bressol_pdp_use_' . $i . '" value="' . esc_attr($value) . '">';
        }

        echo '<p><strong>Pairing tips (3 bullets)</strong></p>';
        for ($i = 1; $i <= 3; $i++) {
            $value = $pairItems[$i - 1] ?? '';
            echo '<input class="widefat" type="text" maxlength="120" name="bressol_pdp_pair_' . $i . '" value="' . esc_attr($value) . '">';
        }

        echo '<p><strong>Oorsprong (kort)</strong></p>';
        echo '<textarea class="large-text" name="bressol_pdp_origin_text" rows="3" maxlength="500">' . esc_textarea($originText) . '</textarea>';

        echo '<p><strong>Bewaren (kort)</strong></p>';
        echo '<textarea class="large-text" name="bressol_pdp_storage_text" rows="2" maxlength="500">' . esc_textarea($storageText) . '</textarea>';

        echo '<p><strong>Smaakprofiel</strong></p>';
        echo '<p><em>Textuur (3)</em></p>';
        for ($i = 1; $i <= 3; $i++) {
            $value = $textureItems[$i - 1] ?? '';
            echo '<input class="widefat" type="text" maxlength="120" name="bressol_smaak_textuur' . $i . '" value="' . esc_attr($value) . '">';
        }
        echo '<p><em>Ingrediënten (3)</em></p>';
        for ($i = 1; $i <= 3; $i++) {
            $value = $ingredientItems[$i - 1] ?? '';
            echo '<input class="widefat" type="text" maxlength="120" name="bressol_smaak_ingredienten' . $i . '" value="' . esc_attr($value) . '">';
        }
        echo '<p><em>Karakter (3)</em></p>';
        for ($i = 1; $i <= 3; $i++) {
            $value = $characterItems[$i - 1] ?? '';
            echo '<input class="widefat" type="text" maxlength="120" name="bressol_smaak_karakter' . $i . '" value="' . esc_attr($value) . '">';
        }

        echo '<hr style="margin:16px 0;">';
        echo '<h3>Ingrediënten & allergenen</h3>';
        echo '<p><strong>Ingrediënten (volledig)</strong></p>';
        echo '<textarea class="large-text" name="bressol_ingredients_full" rows="6" maxlength="2000">' . esc_textarea($ingredientsFull) . '</textarea>';

        echo '<p><strong>Allergenen</strong></p>';
        foreach ($allergenOptions as $key => $label) {
            $checked = in_array($key, $allergens, true) ? ' checked' : '';
            echo '<label style="display:block;margin-bottom:4px;">';
            echo '<input type="checkbox" name="bressol_allergens[]" value="' . esc_attr($key) . '"' . $checked . '>';
            echo ' ' . esc_html($label) . '</label>';
        }

        echo '<p><strong>Kan sporen bevatten van</strong></p>';
        foreach ($allergenOptions as $key => $label) {
            $checked = in_array($key, $mayContain, true) ? ' checked' : '';
            echo '<label style="display:block;margin-bottom:4px;">';
            echo '<input type="checkbox" name="bressol_may_contain[]" value="' . esc_attr($key) . '"' . $checked . '>';
            echo ' ' . esc_html($label) . '</label>';
        }
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

        for ($i = 1; $i <= 4; $i++) {
            $value = isset($_POST['bressol_pdp_trust_' . $i]) ? wp_unslash($_POST['bressol_pdp_trust_' . $i]) : '';
            update_post_meta($postId, 'bressol_pdp_trust_' . $i, $this->sanitizeLine($value, 120));
        }

        for ($i = 1; $i <= 3; $i++) {
            $value = isset($_POST['bressol_pdp_use_' . $i]) ? wp_unslash($_POST['bressol_pdp_use_' . $i]) : '';
            update_post_meta($postId, 'bressol_pdp_use_' . $i, $this->sanitizeLine($value, 120));
        }

        for ($i = 1; $i <= 3; $i++) {
            $value = isset($_POST['bressol_pdp_pair_' . $i]) ? wp_unslash($_POST['bressol_pdp_pair_' . $i]) : '';
            update_post_meta($postId, 'bressol_pdp_pair_' . $i, $this->sanitizeLine($value, 120));
        }

        $originText = isset($_POST['bressol_pdp_origin_text']) ? wp_unslash($_POST['bressol_pdp_origin_text']) : '';
        $storageText = isset($_POST['bressol_pdp_storage_text']) ? wp_unslash($_POST['bressol_pdp_storage_text']) : '';
        update_post_meta($postId, 'bressol_pdp_origin_text', $this->sanitizeText($originText, 500));
        update_post_meta($postId, 'bressol_pdp_storage_text', $this->sanitizeText($storageText, 500));

        for ($i = 1; $i <= 3; $i++) {
            $value = isset($_POST['bressol_smaak_textuur' . $i]) ? wp_unslash($_POST['bressol_smaak_textuur' . $i]) : '';
            update_post_meta($postId, 'bressol_smaak_textuur' . $i, $this->sanitizeLine($value, 120));
        }
        for ($i = 1; $i <= 3; $i++) {
            $value = isset($_POST['bressol_smaak_ingredienten' . $i]) ? wp_unslash($_POST['bressol_smaak_ingredienten' . $i]) : '';
            update_post_meta($postId, 'bressol_smaak_ingredienten' . $i, $this->sanitizeLine($value, 120));
        }
        for ($i = 1; $i <= 3; $i++) {
            $value = isset($_POST['bressol_smaak_karakter' . $i]) ? wp_unslash($_POST['bressol_smaak_karakter' . $i]) : '';
            update_post_meta($postId, 'bressol_smaak_karakter' . $i, $this->sanitizeLine($value, 120));
        }

        $ingredientsFull = isset($_POST['bressol_ingredients_full']) ? wp_unslash($_POST['bressol_ingredients_full']) : '';
        update_post_meta($postId, 'bressol_ingredients_full', $this->sanitizeText($ingredientsFull, 2000));

        $allergensRaw = isset($_POST['bressol_allergens']) ? (array) wp_unslash($_POST['bressol_allergens']) : [];
        $mayContainRaw = isset($_POST['bressol_may_contain']) ? (array) wp_unslash($_POST['bressol_may_contain']) : [];
        update_post_meta($postId, 'bressol_allergens', $this->sanitizeAllergenArray($allergensRaw));
        update_post_meta($postId, 'bressol_may_contain', $this->sanitizeAllergenArray($mayContainRaw));
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

    private function sanitizeLine($value, int $maxLength): string
    {
        $line = sanitize_text_field((string) $value);
        $line = trim($line);
        if ($line === '') {
            return '';
        }
        return mb_substr($line, 0, $maxLength);
    }

    private function sanitizeText($value, int $maxLength): string
    {
        $text = sanitize_textarea_field((string) $value);
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        return mb_substr($text, 0, $maxLength);
    }

    /** @return array<int, string> */
    private function sanitizeAllergenArray(array $values): array
    {
        $allowed = array_keys($this->getAllergenOptions());
        $out = [];
        foreach ($values as $value) {
            $key = sanitize_key((string) $value);
            if (in_array($key, $allowed, true)) {
                $out[] = $key;
            }
        }
        return array_values(array_unique($out));
    }

    /** @return array<int, string> */
    private function getMetaLines(int $postId, string $prefix, int $count): array
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = (string) get_post_meta($postId, $prefix . $i, true);
        }
        return $items;
    }

    /** @return array<int, string> */
    private function getMetaArray(int $postId, string $key): array
    {
        $value = get_post_meta($postId, $key, true);
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_map('strval', $value));
    }

    /** @return array<string, string> */
    private function getAllergenOptions(): array
    {
        return [
            'gluten_cereals' => 'Glutenbevattende granen',
            'crustaceans' => 'Schaaldieren',
            'eggs' => 'Eieren',
            'fish' => 'Vis',
            'peanuts' => 'Pinda’s',
            'soybeans' => 'Soja',
            'milk' => 'Melk',
            'nuts' => 'Noten',
            'celery' => 'Selderij',
            'mustard' => 'Mosterd',
            'sesame' => 'Sesam',
            'sulphites' => 'Zwaveldioxide en sulfieten',
            'lupin' => 'Lupine',
            'molluscs' => 'Weekdieren',
        ];
    }

    private function sanitizeCsv(string $value): string
    {
        return \Bressol\Modules\Seo\SeoMetaModule::normalizeCsv($value);
    }
}
