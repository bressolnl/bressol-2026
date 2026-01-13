<?php
declare(strict_types=1);

namespace Bressol\Modules\Packs\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class PackAttributesMetaBox
{
    private const META_TIER     = '_bressol_pack_tier';
    private const META_OCCASION = '_bressol_pack_occasion';
    private const META_THEMES   = '_bressol_pack_themes'; // CSV: borrel,dessert,paella,oorsprong,corporate,cooking
    private const META_FOCUS    = '_bressol_pack_focus';  // CSV: oil,salt,vinegar | borrel,olives,tapenade | sweet,...

    private const META_PACK_DEFINITION = '_bressol_pack_definition';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post_product', [$this, 'save']);
    }

    public function addMetaBox(): void
    {
        add_meta_box(
            'bressol_pack_attributes',
            'Bressol Pack Attributes',
            [$this, 'render'],
            'product',
            'side',
            'default'
        );
    }

    public function render(\WP_Post $post): void
    {
        $isPack = (bool) get_post_meta($post->ID, self::META_PACK_DEFINITION, true);

        $tier     = (string) get_post_meta($post->ID, self::META_TIER, true);
        $occasion = (string) get_post_meta($post->ID, self::META_OCCASION, true);
        $themes   = (string) get_post_meta($post->ID, self::META_THEMES, true);
        $focus    = (string) get_post_meta($post->ID, self::META_FOCUS, true);

        wp_nonce_field('bressol_pack_attributes_save', 'bressol_pack_attributes_nonce');

        if (!$isPack) {
            echo '<p style="margin:0;color:#666;">Este producto no es un pack (no tiene <code>_bressol_pack_definition</code>).</p>';
            echo '<p style="margin:8px 0 0;color:#666;">No se guardarán atributos.</p>';
            return;
        }

        echo '<p style="margin:0 0 8px;color:#666;">Atributos usados para recomendaciones y wizard.</p>';

        // Tier
        echo '<p style="margin:10px 0 6px;"><strong>Tier</strong></p>';
        echo '<select name="bressol_pack_tier" style="width:100%;">';
        echo '<option value="">(sin definir)</option>';
        echo '<option value="low"' . selected($tier, 'low', false) . '>Low</option>';
        echo '<option value="mid"' . selected($tier, 'mid', false) . '>Mid</option>';
        echo '<option value="high"' . selected($tier, 'high', false) . '>High / Premium</option>';
        echo '</select>';

        // Occasion
        echo '<p style="margin:10px 0 6px;"><strong>Occasion fit</strong></p>';
        echo '<select name="bressol_pack_occasion" style="width:100%;">';
        echo '<option value="">(sin definir)</option>';
        echo '<option value="gift"' . selected($occasion, 'gift', false) . '>Gift</option>';
        echo '<option value="self"' . selected($occasion, 'self', false) . '>Self</option>';
        echo '<option value="both"' . selected($occasion, 'both', false) . '>Both</option>';
        echo '</select>';

        echo '<hr style="margin:12px 0;">';

        // Themes
        echo '<p style="margin:0 0 6px;"><strong>Themes</strong><br><small style="color:#666;">CSV (ej: borrel,dessert,paella,oorsprong,corporate,cooking)</small></p>';
        echo '<input type="text" name="bressol_pack_themes" value="' . esc_attr($themes) . '" style="width:100%;" />';

        // Focus
        echo '<p style="margin:10px 0 6px;"><strong>Focus</strong><br><small style="color:#666;">CSV (ej: oil,salt,vinegar | borrel,olives,tapenade | sweet)</small></p>';
        echo '<input type="text" name="bressol_pack_focus" value="' . esc_attr($focus) . '" style="width:100%;" />';
    }

    public function save(int $postId): void
    {
        if (!isset($_POST['bressol_pack_attributes_nonce']) ||
            !wp_verify_nonce((string) $_POST['bressol_pack_attributes_nonce'], 'bressol_pack_attributes_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Solo guardar si realmente es pack
        $isPack = (bool) get_post_meta($postId, self::META_PACK_DEFINITION, true);
        if (!$isPack) {
            return;
        }

        // Tier
        if (array_key_exists('bressol_pack_tier', $_POST)) {
            $tier = sanitize_text_field((string) $_POST['bressol_pack_tier']);
            if ($tier === '') delete_post_meta($postId, self::META_TIER);
            else update_post_meta($postId, self::META_TIER, $tier);
        }

        // Occasion
        if (array_key_exists('bressol_pack_occasion', $_POST)) {
            $occ = sanitize_text_field((string) $_POST['bressol_pack_occasion']);
            if ($occ === '') delete_post_meta($postId, self::META_OCCASION);
            else update_post_meta($postId, self::META_OCCASION, $occ);
        }

        // Themes (CSV normalizado a lowercase, sin espacios repetidos)
        if (array_key_exists('bressol_pack_themes', $_POST)) {
            $themes = strtolower(sanitize_text_field((string) $_POST['bressol_pack_themes']));
            $themes = $this->normalizeCsv($themes);
            if ($themes === '') delete_post_meta($postId, self::META_THEMES);
            else update_post_meta($postId, self::META_THEMES, $themes);
        }

        // Focus (CSV normalizado)
        if (array_key_exists('bressol_pack_focus', $_POST)) {
            $focus = strtolower(sanitize_text_field((string) $_POST['bressol_pack_focus']));
            $focus = $this->normalizeCsv($focus);
            if ($focus === '') delete_post_meta($postId, self::META_FOCUS);
            else update_post_meta($postId, self::META_FOCUS, $focus);
        }
    }

    private function normalizeCsv(string $csv): string
    {
        $parts = array_filter(array_map('trim', explode(',', $csv)));
        $parts = array_values(array_unique($parts));
        return implode(',', $parts);
    }
}