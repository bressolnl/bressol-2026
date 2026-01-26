<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\Adapters;

use Bressol\Modules\Purchasing\Services\Ports\ForecastingPort;

if (!defined('ABSPATH')) {
    exit;
}

final class ForecastingAdapter implements ForecastingPort
{
    private const SERVICE_CLASS = '\\Bressol\\Modules\\Forecasting\\Services\\ForecastingService';

    public function is_available(): bool
    {
        return class_exists(self::SERVICE_CLASS);
    }

    public function get_forecast(string $windowStartUtc, string $windowEndUtc): array
    {
        if (!$this->is_available()) {
            return [];
        }

        $service = new self::SERVICE_CLASS();
        if (!method_exists($service, 'get_forecast')) {
            return [];
        }

        try {
            $result = $service->get_forecast($windowStartUtc, $windowEndUtc);
        } catch (\Throwable $exception) {
            return [];
        }

        return is_array($result) ? $this->normalize_map($result) : [];
    }

    /** @param array<mixed, mixed> $input
     *  @return array<string, int>
     */
    private function normalize_map(array $input): array
    {
        $map = [];
        foreach ($input as $key => $value) {
            $normalizedKey = $this->normalize_key($key);
            if ($normalizedKey === '') {
                continue;
            }
            $qty = is_numeric($value) ? (int) $value : 0;
            $map[$normalizedKey] = $qty;
        }

        return $map;
    }

    private function normalize_key($key): string
    {
        if (is_int($key) || (is_string($key) && ctype_digit($key))) {
            return 'product_id:' . (int) $key;
        }

        $raw = is_string($key) ? trim($key) : '';
        if ($raw === '') {
            return '';
        }

        return 'sku:' . $raw;
    }
}
