<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services;

use Bressol\Modules\Forecasting\Repositories\ForecastSnapshotRepository;
use Bressol\Modules\MarketsEvents\Repositories\EventRepository;
use Bressol\Modules\Forecasting\Services\Ports\LeadTimePort;
use Bressol\Modules\Forecasting\Services\Ports\NullLeadTimePort;
use Bressol\Modules\Forecasting\Services\Ports\PurchasingLeadTimePort;
use Bressol\Modules\Inventory\Repositories\ProductRepository;
use Bressol\Modules\Inventory\Services\StockCalculator;

if (!defined('ABSPATH')) {
    exit;
}

final class ForecastCalculator
{
    private ForecastWindow $window;
    private ForecastSnapshotRepository $snapshotRepository;
    private EventRepository $eventRepository;
    private WarningsBuilder $warningsBuilder;
    private LeadTimePort $leadTimePort;

    public function __construct(
        ?ForecastWindow $window = null,
        ?ForecastSnapshotRepository $snapshotRepository = null,
        ?EventRepository $eventRepository = null,
        ?WarningsBuilder $warningsBuilder = null,
        ?LeadTimePort $leadTimePort = null
    ) {
        $this->window = $window ?? new ForecastWindow();
        $this->snapshotRepository = $snapshotRepository ?? new ForecastSnapshotRepository();
        $this->eventRepository = $eventRepository ?? new EventRepository();
        $this->warningsBuilder = $warningsBuilder ?? new WarningsBuilder();
        $this->leadTimePort = $leadTimePort ?? $this->build_lead_time_port();
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $window = $this->window->get_window();
        $weeks = $window['weeks'];

        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $toUtcRaw = isset($window['to_utc']) ? (string) $window['to_utc'] : '';
        $toUtc = $toUtcRaw !== ''
            ? \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $toUtcRaw, new \DateTimeZone('UTC'))
            : null;
        $isPastWindow = $toUtc instanceof \DateTimeImmutable && $toUtc <= $nowUtc;

        if ($isPastWindow) {
            $events = [];
            $seen = [];
            foreach (['completed', 'confirmed', 'paid'] as $status) {
                $rows = $this->eventRepository->find_by_filters([
                    'status' => $status,
                    'date_from' => $window['from_utc'],
                    'date_to' => $window['to_utc'],
                ], 200, 1);
                if (!is_array($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    $id = isset($row['id']) ? (int) $row['id'] : 0;
                    if ($id <= 0 || isset($seen[$id])) {
                        continue;
                    }
                    $events[] = $row;
                    $seen[$id] = true;
                }
            }
        } else {
            $events = $this->eventRepository->find_by_filters([
                'status' => 'confirmed',
                'date_from' => $window['from_utc'],
                'date_to' => $window['to_utc'],
            ], 200, 1);
        }
        if (!is_array($events)) {
            $events = [];
        }
        $fallbackUsed = false;
        if ($events === []) {
            $events = $this->eventRepository->find_by_filters([
                'status' => 'planned',
                'date_from' => $window['from_utc'],
                'date_to' => $window['to_utc'],
            ], 200, 1);
            $fallbackUsed = $events !== [];
            if (!is_array($events)) {
                $events = [];
                $fallbackUsed = false;
            }
        }

        $aggregator = new WeeklyAggregator();
        $missingSnapshots = [];
        $emptySnapshots = [];

        foreach ($events as $event) {
            $eventId = isset($event['id']) ? (int) $event['id'] : 0;
            if ($eventId <= 0) {
                continue;
            }

            $eventDateUtc = $this->resolve_event_start_utc($event);
            if ($eventDateUtc === null) {
                continue;
            }

            $weekKey = $this->window->resolve_week_key($eventDateUtc, $weeks);
            if ($weekKey === null) {
                continue;
            }

            $snapshot = $this->snapshotRepository->get_latest_snapshot_for_event($eventId);
            if (!$snapshot) {
                $missingSnapshots[] = $eventId;
                continue;
            }

            $snapshotId = (int) ($snapshot['id'] ?? 0);
            $lines = $snapshotId > 0 ? $this->snapshotRepository->get_lines_for_snapshot($snapshotId) : [];
            if ($lines === []) {
                $emptySnapshots[] = $eventId;
                continue;
            }

            $aggregator->add_lines($weekKey, $lines);
        }

        $weekly = $aggregator->get_weekly();
        $totals = $aggregator->get_totals();
        $sellable = $this->get_sellable_units(array_keys($totals));
        $purchasePlan = $this->build_purchase_plan($totals, $sellable);
        $leadTimeDays = $this->leadTimePort->get_lead_time_days();

        $warnings = $this->warningsBuilder->build([
            'events_count' => count($events),
            'events_fallback_used' => $fallbackUsed,
            'missing_snapshots' => $missingSnapshots,
            'empty_snapshots' => $emptySnapshots,
            'unknown_stock' => $sellable['unknown'] ?? [],
            'inventory_fallback_used' => !empty($sellable['inventory_fallback_used']),
        ]);

        return [
            'window' => $window,
            'weekly_forecast' => $weekly,
            'totals' => $totals,
            'purchase_plan' => $purchasePlan,
            'warnings' => $warnings,
            'lead_time_days' => $leadTimeDays,
        ];
    }

    /** @param array<string, mixed> $event */
    private function resolve_event_start_utc(array $event): ?\DateTimeImmutable
    {
        $start = isset($event['start_at']) ? (string) $event['start_at'] : '';
        if ($start === '') {
            return null;
        }

        $timezone = isset($event['timezone']) ? (string) $event['timezone'] : 'UTC';
        try {
            $tz = new \DateTimeZone($timezone !== '' ? $timezone : 'UTC');
        } catch (\Throwable $exception) {
            $tz = new \DateTimeZone('UTC');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start, $tz);
        if (!$date) {
            return null;
        }

        return $date->setTimezone(new \DateTimeZone('UTC'));
    }

    /** @param int[] $productIds
     *  @return array<string, mixed>
     */
    private function get_sellable_units(array $productIds): array
    {
        $sellable = [];
        $unknown = [];
        $inventoryFallback = false;

        $inventorySellable = $this->get_sellable_units_from_inventory($productIds);
        if ($inventorySellable !== null) {
            return $inventorySellable;
        }

        $inventoryFallback = true;

        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }

            if (!function_exists('wc_get_product')) {
                $sellable[$productId] = 0;
                $unknown[] = $productId;
                continue;
            }

            $product = wc_get_product($productId);
            if (!$product instanceof \WC_Product) {
                $sellable[$productId] = 0;
                $unknown[] = $productId;
                continue;
            }

            if ($product->get_manage_stock()) {
                $qty = $product->get_stock_quantity();
                if ($qty === null) {
                    $sellable[$productId] = 0;
                    $unknown[] = $productId;
                    continue;
                }
                $sellable[$productId] = max(0, (int) $qty);
                continue;
            }

            if ($product->is_in_stock()) {
                $sellable[$productId] = 0;
                $unknown[] = $productId;
                continue;
            }

            $sellable[$productId] = 0;
        }

        return [
            'map' => $sellable,
            'unknown' => $unknown,
            'inventory_fallback_used' => $inventoryFallback,
        ];
    }

    /** @param int[] $productIds
     *  @return array<string, mixed>|null
     */
    private function get_sellable_units_from_inventory(array $productIds): ?array
    {
        if (!class_exists(StockCalculator::class) || !class_exists(ProductRepository::class)) {
            return null;
        }

        try {
            $calculator = new StockCalculator(new ProductRepository());
        } catch (\Throwable $exception) {
            return null;
        }
        $sellable = [];
        $unknown = [];

        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }

            try {
                $qty = $calculator->get_simple_sellable_qty($productId);
            } catch (\Throwable $exception) {
                return null;
            }
            if ($qty === PHP_INT_MAX) {
                $sellable[$productId] = 0;
                $unknown[] = $productId;
                continue;
            }

            $sellable[$productId] = max(0, (int) $qty);
        }

        return [
            'map' => $sellable,
            'unknown' => $unknown,
            'inventory_fallback_used' => false,
        ];
    }

    private function build_lead_time_port(): LeadTimePort
    {
        if (class_exists(PurchasingLeadTimePort::class)) {
            return new PurchasingLeadTimePort();
        }

        return new NullLeadTimePort();
    }

    /** @param array<int, int> $totals
     *  @param array<string, mixed> $sellable
     *  @return array<int, array<string, int>>
     */
    private function build_purchase_plan(array $totals, array $sellable): array
    {
        $plan = [];
        $sellableMap = isset($sellable['map']) && is_array($sellable['map']) ? $sellable['map'] : [];

        foreach ($totals as $productId => $forecastTotal) {
            $forecastTotal = (int) $forecastTotal;
            $available = isset($sellableMap[$productId]) ? (int) $sellableMap[$productId] : 0;
            $buy = max(0, $forecastTotal - $available);
            $plan[$productId] = [
                'forecast_total' => $forecastTotal,
                'sellable' => $available,
                'buy' => $buy,
            ];
        }

        return $plan;
    }
}
