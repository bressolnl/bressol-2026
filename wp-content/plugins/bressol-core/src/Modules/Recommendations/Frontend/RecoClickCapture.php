<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class RecoClickCapture
{
    public function register(): void
    {
        add_action('template_redirect', [$this, 'capture'], 1);
    }

    public function capture(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        if (!isset($_GET['bressol_reco_src'])) {
            return;
        }

        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $src  = (int) $_GET['bressol_reco_src'];
        $type = isset($_GET['bressol_reco_type']) ? sanitize_text_field((string) $_GET['bressol_reco_type']) : '';

        $target = (int) get_queried_object_id();
        if ($src <= 0 || $target <= 0) {
            return;
        }

        WC()->session->set('bressol_datalayer_reco_click', [
            'context' => 'pdp',
            'source_product_id' => (string) $src,
            'recommended_product_id' => (string) $target,
            'reco_type' => $type ?: null,
        ]);

        // Opcional pero recomendado: limpiar la URL (SEO/estética)
        $clean = remove_query_arg(['bressol_reco_src', 'bressol_reco_type']);
        if ($clean) {
            wp_safe_redirect($clean);
            exit;
        }
    }
}