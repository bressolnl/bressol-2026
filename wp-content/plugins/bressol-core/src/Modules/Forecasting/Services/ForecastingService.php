<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services;

use Bressol\Modules\Forecasting\Repositories\ForecastSnapshotRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ForecastingService
{
    private Settings $settings;
    private ForecastSnapshotRepository $repository;

    public function __construct(?Settings $settings = null, ?ForecastSnapshotRepository $repository = null)
    {
        $this->settings = $settings ?? new Settings();
        $this->repository = $repository ?? new ForecastSnapshotRepository();
    }

    /** @return array<string, int> */
    public function get_forecast(string $fromUtc, string $toUtc): array
    {
        if (!$this->settings->is_enabled()) {
            return [];
        }

        $rows = $this->repository->get_aggregated_forecast($fromUtc, $toUtc);
        if ($rows === []) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $qty = isset($row['qty_units']) ? (int) $row['qty_units'] : 0;
            if ($productId <= 0 || $qty < 0) {
                continue;
            }
            $map['product_id:' . $productId] = $qty;
        }

        return $map;
    }
}
