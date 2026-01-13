<?php
declare(strict_types=1);

namespace Bressol\Modules\PostAddToCartModal;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\PostAddToCartModal\Frontend\ModalController;

if (!defined('ABSPATH')) exit;

final class PostAddToCartModalModule implements ModuleInterface
{
    public function register(): void
{
    if (!class_exists('\WooCommerce')) {
        return;
    }

    // IMPORTANTE: en admin-ajax.php is_admin() = true, pero necesitamos registrar AJAX.
    $doingAjax = defined('DOING_AJAX') && DOING_AJAX;

    // Solo evitamos el frontend “normal” del admin, pero permitimos AJAX.
    if (is_admin() && !$doingAjax) {
        return;
    }

    (new ModalController())->register();
}
}