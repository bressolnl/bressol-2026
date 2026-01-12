<?php
declare(strict_types=1);

namespace Bressol\Modules\Packs\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class PackMetaBox
{
    private const META_KEY = '_bressol_pack_definition';
    private const AJAX_NONCE_ACTION = 'bressol_pack_definition_ajax_save';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);

        // Guardado clásico (si el editor envía el campo)
        add_action('save_post_product', [$this, 'saveMetaBox']);

        // Guardado robusto vía AJAX (funciona también con editor de bloques)
        add_action('wp_ajax_bressol_save_pack_definition', [$this, 'ajaxSavePackDefinition']);

        // Cargar JS solo en pantalla de producto
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function addMetaBox(): void
    {
        add_meta_box(
            'bressol_pack_definition',
            'Bressol Pack Definition (JSON)',
            [$this, 'renderMetaBox'],
            'product',
            'normal',
            'high'
        );
    }

    public function enqueueAdminAssets(string $hook): void
    {
        // Solo en editar/crear producto
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'product') {
            return;
        }

        $src = plugins_url(
            'src/Modules/Packs/Admin/assets/pack-metabox.js',
            WP_PLUGIN_DIR . '/bressol-core/bressol-core.php'
        );

        wp_enqueue_script('bressol-pack-metabox', $src, [], '0.1.0', true);
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $value = (string) get_post_meta($post->ID, self::META_KEY, true);

        // Nonce para el guardado clásico (si aplica)
        wp_nonce_field('bressol_pack_definition_save', 'bressol_pack_definition_nonce');

        // Nonce para el guardado AJAX
        $ajaxNonce = wp_create_nonce(self::AJAX_NONCE_ACTION);

        echo '<p>Define los slots del pack en JSON. Si está vacío, este producto NO es un pack.</p>';

        echo '<textarea style="width:100%;min-height:260px;font-family:monospace;" name="bressol_pack_definition">'
            . esc_textarea($value) . '</textarea>';

        echo '<div style="margin-top:10px;display:flex;gap:12px;align-items:center;">';
        echo '<button type="button" class="button button-primary" id="bressol-pack-save" data-post-id="'
            . esc_attr((string)$post->ID) . '" data-nonce="' . esc_attr($ajaxNonce) . '">Guardar Pack JSON</button>';
        echo '<span id="bressol-pack-save-status" style="color:#555;"></span>';
        echo '</div>';

        echo '<p style="margin-top:12px;"><strong>Ejemplo:</strong></p>';
        echo '<pre style="background:#f6f6f6;padding:12px;border:1px solid #ddd;white-space:pre-wrap;">'
            . esc_html($this->exampleJson())
            . '</pre>';
    }

    public function saveMetaBox(int $postId): void
    {
        // Guardado clásico: solo si llega el campo
        if (!isset($_POST['bressol_pack_definition_nonce']) ||
            !wp_verify_nonce((string) $_POST['bressol_pack_definition_nonce'], 'bressol_pack_definition_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        if (!array_key_exists('bressol_pack_definition', $_POST)) {
            return;
        }

        $raw = trim((string) $_POST['bressol_pack_definition']);

        if ($raw === '') {
            delete_post_meta($postId, self::META_KEY);
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        update_post_meta($postId, self::META_KEY, wp_json_encode($decoded));
    }

    public function ajaxSavePackDefinition(): void
    {
        if (!current_user_can('edit_products')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $nonce  = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        $json = isset($_POST['json']) ? (string) $_POST['json'] : '';
        $json = trim(wp_unslash($json)); // IMPORTANTE: quitar slashes de WordPress
        
        if ($postId <= 0) {
            wp_send_json_error(['message' => 'post_id inválido.'], 400);
        }
        
        if (!wp_verify_nonce($nonce, self::AJAX_NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Nonce inválido. Recarga la página.'], 400);
        }
        
        if ($json === '') {
            delete_post_meta($postId, self::META_KEY);
            wp_send_json_success(['message' => 'Pack eliminado (JSON vacío).']);
        }
        
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            wp_send_json_error(['message' => 'JSON inválido: ' . $e->getMessage()], 400);
        }
        
        if (!is_array($decoded)) {
            wp_send_json_error(['message' => 'JSON inválido: debe ser un objeto/array.'], 400);
        }
        
        update_post_meta($postId, self::META_KEY, wp_json_encode($decoded));
        wp_send_json_success(['message' => 'Guardado.']);
    }

    private function exampleJson(): string
    {
        return '{
  "slots": [
    {
      "key": "oil",
      "label": "Elige 1 aceite",
      "required": true,
      "min": 1,
      "max": 1,
      "options": [
        { "product_id": 68, "label": "Aceite (ID 68)", "surcharge": 0 },
        { "product_id": 73, "label": "Aceite premium (ID 73)", "surcharge": 2 }
      ]
    }
  ]
}';
    }
}