<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class WeeklyAggregator
{
    /** @var array<string, array<int, int>> */
    private array $weekly = [];
    /** @var array<int, int> */
    private array $totals = [];

    public function add_lines(string $weekKey, array $lines): void
    {
        if ($weekKey === '') {
            return;
        }
        if (!isset($this->weekly[$weekKey])) {
            $this->weekly[$weekKey] = [];
        }

        foreach ($lines as $line) {
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $qty = isset($line['qty_units']) ? (int) $line['qty_units'] : 0;
            if ($productId <= 0 || $qty < 0) {
                continue;
            }

            $this->weekly[$weekKey][$productId] = ($this->weekly[$weekKey][$productId] ?? 0) + $qty;
            $this->totals[$productId] = ($this->totals[$productId] ?? 0) + $qty;
        }
    }

    /** @return array<string, array<int, int>> */
    public function get_weekly(): array
    {
        ksort($this->weekly);
        return $this->weekly;
    }

    /** @return array<int, int> */
    public function get_totals(): array
    {
        ksort($this->totals);
        return $this->totals;
    }
}
