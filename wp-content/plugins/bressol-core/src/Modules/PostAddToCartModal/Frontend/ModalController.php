<?php
declare(strict_types=1);

namespace Bressol\Modules\PostAddToCartModal\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class ModalController
{
    public function register(): void
    {
        // Guardar último add_to_cart (para poder reemplazar)
        add_action('woocommerce_add_to_cart', [$this, 'captureLastAdded'], 10, 6);

        // Assets (solo frontend)
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // AJAX: suggestions
        add_action('wp_ajax_bressol_get_post_add_to_cart_suggestions', [$this, 'ajaxSuggestions']);
        add_action('wp_ajax_nopriv_bressol_get_post_add_to_cart_suggestions', [$this, 'ajaxSuggestions']);

        // AJAX: upgrade replace
        add_action('wp_ajax_bressol_upgrade_replace_with_pack', [$this, 'ajaxReplaceWithPack']);
        add_action('wp_ajax_nopriv_bressol_upgrade_replace_with_pack', [$this, 'ajaxReplaceWithPack']);

        // AJAX: add extra
        add_action('wp_ajax_bressol_modal_add_extra_to_cart', [$this, 'ajaxAddExtra']);
        add_action('wp_ajax_nopriv_bressol_modal_add_extra_to_cart', [$this, 'ajaxAddExtra']);
    }

    public function captureLastAdded(
        string $cartItemKey,
        int $productId,
        int $quantity,
        int $variationId,
        array $variation,
        array $cartItemData
    ): void {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $slot = $this->detectSlotByCategory($productId);

        $payload = [
            'cart_item_key' => $cartItemKey,
            'product_id'    => (int) $productId,
            'slot'          => $slot, // oil|salt|vinegar|drinks|olives|tapenade|other
            'ts'            => time(),
        ];

        // Último add_to_cart (para AJAX y para validar)
        WC()->session->set('bressol_last_added', $payload);

        // Cola pending (fallback para PDP submit / Blocks: el modal se abrirá en el siguiente render)
        WC()->session->set('bressol_modal_pending', $payload);
    }

    public function enqueueAssets(): void
    {
        if (is_admin()) {
            return;
        }

        // Evitar modal en checkout
        if (function_exists('is_checkout') && is_checkout()) {
            return;
        }

        $handle = 'bressol-post-add-to-cart-modal';
        $src = plugins_url(
            'src/Modules/PostAddToCartModal/Frontend/assets/modal.js',
            WP_PLUGIN_DIR . '/bressol-core/bressol-core.php'
        );

        // Sube versión para bust cache cuando cambies JS
        wp_enqueue_script($handle, $src, ['jquery'], '0.1.1', true);

        // Leer cola "pending" y limpiarla para que no se repita
        $pending = null;
        if (function_exists('WC') && WC()->session) {
            $p = WC()->session->get('bressol_modal_pending');
            if (is_array($p) && !empty($p['product_id'])) {
                $pending = $p;
                WC()->session->__unset('bressol_modal_pending');
            }
        }

        wp_localize_script($handle, 'bressolModal', [
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('bressol_modal'),
            'cooldownMinutes'  => 30,
            'pending'          => $pending,
        ]);
    }

    private function ensureWooSession(): bool
    {
        if (!function_exists('WC')) {
            return false;
        }

        // En admin-ajax a veces WC()->session no está inicializada
        if (!WC()->session && method_exists(WC(), 'initialize_session')) {
            WC()->initialize_session();
        }

        // En algunas instalaciones, para tocar el carrito en AJAX es útil inicializarlo
        if (!WC()->cart && method_exists(WC(), 'initialize_cart')) {
            WC()->initialize_cart();
        }

        return (bool) WC()->session;
    }

    public function ajaxSuggestions(): void
    {
        if (!$this->ensureWooSession()) {
            wp_send_json_error(['message' => 'No session'], 400);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce((string) $_POST['nonce'], 'bressol_modal')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $sourceProductId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if ($sourceProductId <= 0) {
            wp_send_json_error(['message' => 'Missing product_id'], 400);
        }

        $last = WC()->session->get('bressol_last_added');
        if (!is_array($last) || (int) ($last['product_id'] ?? 0) !== $sourceProductId) {
            // Si no coincide, seguimos igualmente pero sin cart_item_key
            $last = [
                'cart_item_key' => '',
                'product_id'    => $sourceProductId,
                'slot'          => $this->detectSlotByCategory($sourceProductId),
            ];
        }

        // 1) Packs compatibles (upgrade)
        $packs = $this->findCompatiblePacks($sourceProductId);

        // 2) Extras (cross-sell)
        $extras = $this->findExtras($sourceProductId, 3);

        // Prioridad: packs primero, y limitar total 3
        $suggestions = [
            'source_product_id' => (string) $sourceProductId,
            'cart_item_key'     => (string) ($last['cart_item_key'] ?? ''),
            'slot'              => (string) ($last['slot'] ?? 'other'),
            'upgrade_packs'     => [],
            'extras'            => [],
        ];

        foreach ($packs as $p) {
            $suggestions['upgrade_packs'][] = $p;
            if (count($suggestions['upgrade_packs']) >= 3) {
                break;
            }
        }

        $remaining = 3 - count($suggestions['upgrade_packs']);
        if ($remaining > 0) {
            foreach ($extras as $e) {
                $suggestions['extras'][] = $e;
                if (count($suggestions['extras']) >= $remaining) {
                    break;
                }
            }
        }

        $hasAny = !empty($suggestions['upgrade_packs']) || !empty($suggestions['extras']);

        wp_send_json_success([
            'has_suggestions' => $hasAny,
            'data'            => $suggestions,
        ]);
    }

    public function ajaxReplaceWithPack(): void
    {
        if (!$this->ensureWooSession() || !WC()->cart) {
            wp_send_json_error(['message' => 'Cart not ready'], 400);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce((string) $_POST['nonce'], 'bressol_modal')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $cartItemKey = isset($_POST['cart_item_key']) ? sanitize_text_field((string) $_POST['cart_item_key']) : '';
        $packId      = isset($_POST['pack_id']) ? (int) $_POST['pack_id'] : 0;
        $sourcePid   = isset($_POST['source_product_id']) ? (int) $_POST['source_product_id'] : 0;
        $slotKey     = isset($_POST['slot']) ? sanitize_text_field((string) $_POST['slot']) : 'other';

        if ($packId <= 0 || $sourcePid <= 0) {
            wp_send_json_error(['message' => 'Invalid payload'], 400);
        }

        // Leer config enviada (slot => product_id). Si no viene, defaults.
        $config = [];
        if (isset($_POST['config_json'])) {
            $decoded = json_decode((string) $_POST['config_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    $config[(string) $k] = (int) $v;
                }
            }
        }

        if (empty($config)) {
            $config = $this->buildDefaultPackConfig($packId, $slotKey, $sourcePid);
        } else {
            // Asegurar prefill del slot del producto origen
            if ($slotKey !== 'other') {
                $config[$slotKey] = $sourcePid;
            }
        }

        // 1) Remove item original (si existe)
        if ($cartItemKey !== '' && isset(WC()->cart->get_cart()[$cartItemKey])) {
            WC()->cart->remove_cart_item($cartItemKey);
        }

        // 2) Add pack con meta de config
        $cartItemData = [
            'bressol_pack_config' => $config,
            'bressol_upgrade'     => [
                'src'               => 'modal',
                'source_product_id' => (string) $sourcePid,
            ],
        ];

        $addedKey = WC()->cart->add_to_cart($packId, 1, 0, [], $cartItemData);
        if (!$addedKey) {
            wp_send_json_error(['message' => 'Failed to add pack'], 500);
        }

        // Devuelve fragments JSON y termina
        \WC_AJAX::get_refreshed_fragments();
        wp_die();
    }

    public function ajaxAddExtra(): void
    {
        if (!$this->ensureWooSession() || !WC()->cart) {
            wp_send_json_error(['message' => 'Cart not ready'], 400);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce((string) $_POST['nonce'], 'bressol_modal')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if ($productId <= 0) {
            wp_send_json_error(['message' => 'Invalid product_id'], 400);
        }

        $ok = WC()->cart->add_to_cart($productId, 1);
        if (!$ok) {
            wp_send_json_error(['message' => 'Failed to add extra'], 500);
        }

        \WC_AJAX::get_refreshed_fragments();
        wp_die();
    }

    private function detectSlotByCategory(int $productId): string
    {
        $terms = get_the_terms($productId, 'product_cat');
        if (!is_array($terms)) {
            return 'other';
        }

        $slugs = array_map(fn($t) => strtolower((string) $t->slug), $terms);

        if (in_array('oil', $slugs, true)) return 'oil';
        if (in_array('salt', $slugs, true)) return 'salt';
        if (in_array('vinegar', $slugs, true)) return 'vinegar';
        if (in_array('drinks', $slugs, true)) return 'drinks';
        if (in_array('olives', $slugs, true)) return 'olives';
        if (in_array('tapenade', $slugs, true)) return 'tapenade';

        return 'other';
    }

    /**
     * Packs compatibles = packs cuyo JSON contiene un slot con options que incluye sourcePid.
     * Devuelve packs con: pack_id, title, price, currency, reason, prefill_slot, slots (para UI del modal)
     */
    private function findCompatiblePacks(int $sourcePid): array
    {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => '_bressol_pack_definition', 'compare' => 'EXISTS'],
            ],
        ];

        $ids = get_posts($args);
        if (!is_array($ids) || !$ids) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $packId = (int) $id;

            $json = (string) get_post_meta($packId, '_bressol_pack_definition', true);
            if ($json === '') {
                continue;
            }

            $def = json_decode($json, true);
            if (!is_array($def) || empty($def['slots']) || !is_array($def['slots'])) {
                continue;
            }

            $prefillSlot = '';
            foreach ($def['slots'] as $slot) {
                if (!is_array($slot)) continue;
                $key = (string) ($slot['key'] ?? '');
                $options = (array) ($slot['options'] ?? []);
                foreach ($options as $opt) {
                    if ((int) ($opt['product_id'] ?? 0) === $sourcePid) {
                        $prefillSlot = $key;
                        break 2;
                    }
                }
            }

            if ($prefillSlot === '') {
                continue;
            }

            $p = wc_get_product($packId);
            if (!$p) {
                continue;
            }

            $out[] = [
                'pack_id'      => (string) $packId,
                'title'        => $p->get_name(),
                'price'        => (float) $p->get_price(),
                'currency'     => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR',
                'reason'       => 'Mejora tu compra con un pack (upgrade).',
                'prefill_slot' => $prefillSlot,
                'slots'        => $this->normalizeSlotsForUi($def['slots']),
            ];
        }

        // Ordenar por precio ascendente
        usort($out, function (array $a, array $b): int {
            return ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0));
        });

        return $out;
    }

    private function normalizeSlotsForUi(array $slots): array
    {
        $out = [];

        foreach ($slots as $slot) {
            if (!is_array($slot)) continue;

            $key = (string) ($slot['key'] ?? '');
            if ($key === '') continue;

            $label = (string) ($slot['label'] ?? $key);
            $optionsOut = [];

            foreach ((array) ($slot['options'] ?? []) as $opt) {
                if (!is_array($opt)) continue;

                $pid = (int) ($opt['product_id'] ?? 0);
                if ($pid <= 0) continue;

                $p = wc_get_product($pid);
                if (!$p) continue;

                $optionsOut[] = [
                    'product_id' => $pid,
                    'label'      => (string) ($opt['label'] ?? $p->get_name()),
                    'surcharge'  => (float) ($opt['surcharge'] ?? 0),
                ];
            }

            $out[] = [
                'key'     => $key,
                'label'   => $label,
                'options' => $optionsOut,
            ];
        }

        return $out;
    }

    private function buildDefaultPackConfig(int $packId, string $slotKey, int $sourcePid): array
    {
        $json = (string) get_post_meta($packId, '_bressol_pack_definition', true);
        $def = json_decode($json, true);

        $config = [];
        if (!is_array($def) || empty($def['slots']) || !is_array($def['slots'])) {
            return $config;
        }

        foreach ($def['slots'] as $slot) {
            if (!is_array($slot)) continue;

            $key = (string) ($slot['key'] ?? '');
            if ($key === '') continue;

            $options = (array) ($slot['options'] ?? []);
            $selected = (int) ($options[0]['product_id'] ?? 0);

            if ($key === $slotKey) {
                // usar source si está permitido
                foreach ($options as $opt) {
                    if ((int) ($opt['product_id'] ?? 0) === $sourcePid) {
                        $selected = $sourcePid;
                        break;
                    }
                }
            }

            if ($selected > 0) {
                $config[$key] = $selected;
            }
        }

        return $config;
    }

    private function findExtras(int $sourcePid, int $limit): array
    {
        $terms = get_the_terms($sourcePid, 'product_cat');
        $slugs = is_array($terms) ? array_map(fn($t) => strtolower((string) $t->slug), $terms) : [];

        $targetSlugs = [];
        if (in_array('olives', $slugs, true) || in_array('tapenade', $slugs, true)) {
            $targetSlugs = ['drinks'];
        } elseif (in_array('drinks', $slugs, true)) {
            $targetSlugs = ['olives', 'tapenade'];
        } elseif (in_array('oil', $slugs, true)) {
            $targetSlugs = ['salt', 'vinegar'];
        } elseif (in_array('salt', $slugs, true) || in_array('vinegar', $slugs, true)) {
            $targetSlugs = ['oil'];
        }

        if (!$targetSlugs) {
            return [];
        }

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'tax_query'      => [
                ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $targetSlugs],
            ],
        ];

        $ids = get_posts($args);
        if (!is_array($ids)) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $pid = (int) $id;
            if ($pid === $sourcePid) continue;

            $p = wc_get_product($pid);
            if (!$p) continue;

            $out[] = [
                'product_id' => (string) $pid,
                'title'      => $p->get_name(),
                'price'      => (float) $p->get_price(),
                'currency'   => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR',
                'reason'     => 'Ideal para acompañar tu elección.',
            ];
        }

        return $out;
    }
}