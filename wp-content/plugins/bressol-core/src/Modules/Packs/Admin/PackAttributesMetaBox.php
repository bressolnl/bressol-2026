<?php
declare(strict_types=1);

namespace Bressol\Modules\Packs\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class PackAttributesMetaBox
{
    private const META_TIER = '_bressol_pack_tier';
    private const META_OCCASION = '_bressol_pack_occasion';

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
        $tier = (string) get_post_meta($post->ID, self::META_TIER, true);
        $occasion = (string) get_post_meta($post->ID, self::META_OCCASION, true);

        wp_nonce_field('bressol_pack_attributes_save', 'bressol_pack_attributes_nonce');

        echo '<p style="margin:0 0 8px;">Solo aplica a productos que sean packs.</p>';

        echo '<p style="margin:10px 0 6px;"><strong>Tier</strong></p>';
        echo '<select name="bressol_pack_tier" style="width:100%;">';
        echo '<option value="">(sin definir)</option>';
        echo '<option value="low"' . selected($tier, 'low', false) . '>Low</option>';
        echo '<option value="mid"' . selected($tier, 'mid', false) . '>Mid</option>';
        echo '<option value="high"' . selected($tier, 'high', false) . '>High / Premium</option>';
        echo '</select>';

        echo '<p style="margin:10px 0 6px;"><strong>Occasion fit</strong></p>';
        echo '<select name="bressol_pack_occasion" style="width:100%;">';
        echo '<option value="">(sin definir)</option>';
        echo '<option value="gift"' . selected($occasion, 'gift', false) . '>Gift</option>';
        echo '<option value="self"' . selected($occasion, 'self', false) . '>Self</option>';
        echo '<option value="both"' . selected($occasion, 'both', false) . '>Both</option>';
        echo '</select>';
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

        if (array_key_exists('bressol_pack_tier', $_POST)) {
            $tier = sanitize_text_field((string) $_POST['bressol_pack_tier']);
            if ($tier === '') delete_post_meta($postId, self::META_TIER);
            else update_post_meta($postId, self::META_TIER, $tier);
        }

        if (array_key_exists('bressol_pack_occasion', $_POST)) {
            $occ = sanitize_text_field((string) $_POST['bressol_pack_occasion']);
            if ($occ === '') delete_post_meta($postId, self::META_OCCASION);
            else update_post_meta($postId, self::META_OCCASION, $occ);
        }
    }
}