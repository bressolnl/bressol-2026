<?php
declare(strict_types=1);

namespace Bressol\Modules\Recommendations;

use Bressol\Core\ModuleInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class RecommendationsModule implements ModuleInterface
{
    public function register(): void
{
    if (is_admin()) {
        return;
    }

    if (!class_exists('\WooCommerce')) {
        return;
    }

    (new \Bressol\Modules\Recommendations\Frontend\RecoClickCapture())->register();
    (new \Bressol\Modules\Recommendations\Frontend\ProductPageRecommendations())->register();
}
}