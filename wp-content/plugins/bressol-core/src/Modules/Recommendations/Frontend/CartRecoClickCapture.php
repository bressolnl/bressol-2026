<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class CartRecoClickCapture
{
    public function register(): void
    {
        // Muy temprano para capturar antes de render.
        add_action('template_redirect', [$this, 'capture'], 1);
    }

    public function capture(): void
    {
        // Solo nos interesa cuando el usuario aterriza en una PDP con params de carrito
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        if (!isset($_GET['bressol_cart_reco_src'])) {
            return;
        }

        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $type = isset($_GET['bressol_cart_reco_type'])
            ? sanitize_text_field((string) $_GET['bressol_cart_reco_type'])
            : '';

        $targetId = (int) get_queried_object_id();

        WC()->session->set('bressol_datalayer_cart_reco_click', [
            'context' => 'cart',
            'source'  => 'cart',
            'recommended_product_id' => (string) $targetId,
            'reco_type' => $type ?: null,
        ]);

        // Limpiar params para evitar duplicados al recargar
        $clean = remove_query_arg(['bressol_cart_reco_src', 'bressol_cart_reco_type']);
        if (is_string($clean) && $clean !== '') {
            wp_safe_redirect($clean);
            exit;
        }
    }
}