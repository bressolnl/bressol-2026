<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\NullAdapters;

use Bressol\Modules\Purchasing\Services\Ports\ForecastingPort;

if (!defined('ABSPATH')) {
    exit;
}

final class NullForecastingPort implements ForecastingPort
{
    public function is_available(): bool
    {
        return false;
    }

    public function get_forecast(string $windowStartUtc, string $windowEndUtc): array
    {
        return [];
    }
}
