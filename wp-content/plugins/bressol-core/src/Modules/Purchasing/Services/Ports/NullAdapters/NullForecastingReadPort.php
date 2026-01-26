<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\NullAdapters;

use Bressol\Modules\Purchasing\Services\Ports\ForecastingReadPort;

if (!defined('ABSPATH')) {
    exit;
}

final class NullForecastingReadPort implements ForecastingReadPort
{
    public function get_forecast_window(array $productIds, \DateTimeImmutable $fromUtc, \DateTimeImmutable $toUtc): array
    {
        return [];
    }
}
