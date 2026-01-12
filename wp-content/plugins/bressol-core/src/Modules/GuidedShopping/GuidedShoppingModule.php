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
        // Frontend shortcode + handler
        (new \Bressol\Modules\GuidedShopping\Frontend\WizardShortcode())->register();
    }
}