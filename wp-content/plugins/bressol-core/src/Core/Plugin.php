<?php
declare(strict_types=1);

namespace Bressol\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    /** @var array<int, ModuleInterface> */
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
        $this->modules[] = new \Bressol\Modules\PostAddToCartModal\PostAddToCartModalModule();
        $this->modules[] = new \Bressol\Modules\Taxonomies\TaxonomiesModule();
        $this->modules[] = new \Bressol\Modules\Seo\SeoMetaModule();
        $this->modules[] = new \Bressol\Modules\Esp\EspModule();
        $this->modules[] = new \Bressol\Modules\Crm\CrmModule();
        $this->modules[] = new \Bressol\Modules\Pos\PosModule();
        $this->modules[] = new \Bressol\Modules\SalesAnalytics\SalesAnalyticsModule();
        $this->modules[] = new \Bressol\Modules\MarketsEvents\MarketsEventsModule();
        $this->modules[] = new \Bressol\Modules\Inventory\InventoryModule();
    }
}