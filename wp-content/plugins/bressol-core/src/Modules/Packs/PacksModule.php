<?php
declare(strict_types=1);

namespace Bressol\Modules\Packs;

use Bressol\Core\ModuleInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class PacksModule implements ModuleInterface
{
    public function register(): void
    {
        // Admin: metabox para definir packs (JSON)
        if (is_admin()) {
            (new \Bressol\Modules\Packs\Admin\PackMetaBox())->register();
            (new \Bressol\Modules\Packs\Admin\PackAttributesMetaBox())->register();
        }

        // Frontend: UI del pack en ficha de producto
        (new \Bressol\Modules\Packs\Frontend\PackForm())->register();

        // WooCommerce: validación + carrito + precio + pedido
        if (class_exists('\WooCommerce')) {
            (new \Bressol\Modules\Packs\Woo\PackCart())->register();
        }
    }
}