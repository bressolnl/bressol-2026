<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface ForecastingReadPort
{
    /** @param int[] $productIds
     *  @return array<int, array<string, mixed>>
     */
    public function get_forecast_window(array $productIds, \DateTimeImmutable $fromUtc, \DateTimeImmutable $toUtc): array;
}
