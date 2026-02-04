<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class EventCostService
{
    private const OPTION_PREFIX = 'bressol_event_costs_';
    private EventRepository $repository;

    public function __construct(?EventRepository $repository = null)
    {
        $this->repository = $repository ?? new EventRepository();
    }

    public function get_event_total_cost_cents(int $eventId): int
    {
        $costs = $this->get_event_costs($eventId);
        return array_sum($costs);
    }

    public function get_event_daily_cost_cents(int $eventId): int
    {
        $total = $this->get_event_total_cost_cents($eventId);
        $days = $this->get_event_days($eventId);
        if ($days <= 0) {
            return $total;
        }

        return (int) round($total / $days);
    }

    public function get_event_days(int $eventId): int
    {
        $event = $this->repository->find_by_id($eventId);
        if (!$event) {
            return 1;
        }

        $startRaw = (string) ($event['start_at'] ?? '');
        $endRaw = (string) ($event['end_at'] ?? '');
        if ($startRaw === '' || $endRaw === '') {
            return 1;
        }

        $tz = new \DateTimeZone('Europe/Amsterdam');
        $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startRaw, $tz);
        $end = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endRaw, $tz);
        if (!$start || !$end) {
            return 1;
        }

        $startDate = $start->setTime(0, 0, 0);
        $endDate = $end->setTime(0, 0, 0);
        if ($endDate < $startDate) {
            return 1;
        }

        $days = (int) $startDate->diff($endDate)->days + 1;
        return max(1, $days);
    }

    /** @return array<string, int> */
    private function get_event_costs(int $eventId): array
    {
        if ($eventId <= 0) {
            return $this->get_empty_costs();
        }

        $raw = get_option(self::OPTION_PREFIX . $eventId, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $costs = $this->get_empty_costs();
        foreach ($costs as $key => $value) {
            if (isset($raw[$key]) && is_numeric($raw[$key])) {
                $costs[$key] = max(0, (int) $raw[$key]);
            }
        }

        return $costs;
    }

    /** @return array<string, int> */
    private function get_empty_costs(): array
    {
        return [
            'market_fee_total_cents' => 0,
            'fuel_total_cents' => 0,
            'tolls_total_cents' => 0,
            'parking_total_cents' => 0,
            'other_costs_total_cents' => 0,
        ];
    }
}
