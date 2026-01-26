<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface ForecastingPort
{
    public function is_available(): bool;

    /** @return array<string, int> */
    public function get_forecast(string $windowStartUtc, string $windowEndUtc): array;
}
