<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Services\Ports\ForecastingReadPort;
use Bressol\Modules\Purchasing\Services\Ports\InventoryReadPort;
use Bressol\Modules\Purchasing\Services\Ports\MarketsEventsReadPort;

if (!defined('ABSPATH')) {
    exit;
}

final class PurchasePlanningService
{
    private InventoryReadPort $inventoryPort;
    private ForecastingReadPort $forecastingPort;
    private MarketsEventsReadPort $marketsEventsPort;

    public function __construct(
        InventoryReadPort $inventoryPort,
        ForecastingReadPort $forecastingPort,
        MarketsEventsReadPort $marketsEventsPort
    ) {
        $this->inventoryPort = $inventoryPort;
        $this->forecastingPort = $forecastingPort;
        $this->marketsEventsPort = $marketsEventsPort;
    }

    /** @return array<int, array<string, mixed>> */
    public function recommend(int $windowWeeks = 6, int $reminderWeeksBefore = 3): array
    {
        return [];
    }
}
