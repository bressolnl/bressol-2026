<?php
declare(strict_types=1);

namespace Bressol\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    /** @var ModuleInterface[] */
    private array $modules = [];

    public function __construct()
    {
        $this->bootModules();
    }

    public function register(): void
    {
        foreach ($this->modules as $module) {
            $module->register();
        }
    }

    private function bootModules(): void
    {
        // Aquí añadimos módulos a medida que el proyecto crece.
        $this->modules[] = new \Bressol\Modules\Tracking\TrackingModule();
        $this->modules[] = new \Bressol\Modules\Packs\PacksModule();
        $this->modules[] = new \Bressol\Modules\GuidedShopping\GuidedShoppingModule();
        $this->modules[] = new \Bressol\Modules\Recommendations\RecommendationsModule();
    }
}