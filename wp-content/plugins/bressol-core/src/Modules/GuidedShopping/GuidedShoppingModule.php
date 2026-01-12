<?php
declare(strict_types=1);

namespace Bressol\Modules\GuidedShopping;

use Bressol\Core\ModuleInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class GuidedShoppingModule implements ModuleInterface
{
    public function register(): void
{
    (new \Bressol\Modules\GuidedShopping\Frontend\WizardShortcode())->register();

    if (class_exists('\WooCommerce')) {
        (new \Bressol\Modules\GuidedShopping\Frontend\AddToCartRedirector())->register();
    }
}
}