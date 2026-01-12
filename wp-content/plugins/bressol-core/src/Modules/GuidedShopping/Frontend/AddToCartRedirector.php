<?php
declare(strict_types=1);

namespace Bressol\Modules\GuidedShopping\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class AddToCartRedirector
{
    public function register(): void
    {
        add_filter('woocommerce_add_to_cart_redirect', [$this, 'redirectBackToWizard'], 10, 1);
    }

    public function redirectBackToWizard(string $url): string
{
    // Solo actuamos si viene desde nuestro wizard
    if (!isset($_REQUEST['bressol_gs_return'])) {
        return $url;
    }

    // Si además es un add-to-cart, guardamos un evento "guided" en sesión
    if (function_exists('WC') && WC()->session && isset($_REQUEST['add-to-cart'])) {
        $productId = (int) wp_unslash($_REQUEST['add-to-cart']);

        WC()->session->set('bressol_datalayer_guided_upsell', [
            'upsell_type' => 'product',
            'upsell_id'   => (string) $productId,
            'source'      => 'guided_shopping',
        ]);
    }

    $return = (string) wp_unslash($_REQUEST['bressol_gs_return']);
    $return = esc_url_raw($return);

    return $return ?: $url;
}
}