<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

use Bressol\Modules\Forecasting\Services\PosSalesSnapshotBuilder;
use Bressol\Modules\MarketsEvents\Repositories\EventRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class EventCompletionService
{
    public const CRON_HOOK = 'bressol_markets_events_auto_complete';
    public const CRON_SCHEDULE = 'bressol_15min';
    private const LIMIT = 200;

    private EventRepository $repository;
    private PosSalesSnapshotBuilder $snapshotBuilder;

    public function __construct(
        ?EventRepository $repository = null,
        ?PosSalesSnapshotBuilder $snapshotBuilder = null
    ) {
        $this->repository = $repository ?? new EventRepository();
        $this->snapshotBuilder = $snapshotBuilder ?? new PosSalesSnapshotBuilder();
    }

    public function register(): void
    {
        add_filter('cron_schedules', [self::class, 'register_schedule']);
        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    public static function schedule(): void
    {
        add_filter('cron_schedules', [self::class, 'register_schedule']);
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public static function clear(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function run(): void
    {
        $events = array_merge(
            $this->repository->find_by_filters(['status' => 'confirmed'], self::LIMIT, 1),
            $this->repository->find_by_filters(['status' => 'paid'], self::LIMIT, 1)
        );

        if ($events === []) {
            return;
        }

        $deduped = [];
        foreach ($events as $event) {
            $eventId = (int) ($event['id'] ?? 0);
            if ($eventId <= 0 || isset($deduped[$eventId])) {
                continue;
            }
            $deduped[$eventId] = $event;
        }

        $candidates = array_values($deduped);
        usort($candidates, function (array $a, array $b): int {
            $aKey = $this->get_end_at_sort_key($a);
            $bKey = $this->get_end_at_sort_key($b);
            if ($aKey === $bKey) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            return $aKey <=> $bKey;
        });

        $processed = 0;
        foreach ($candidates as $event) {
            if ($processed >= self::LIMIT) {
                break;
            }
            $eventId = (int) ($event['id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            if (!$this->should_complete_event($event)) {
                continue;
            }

            $updated = $this->repository->update($eventId, [
                'status' => 'completed',
                'updated_at' => current_time('mysql'),
            ]);
            if (!$updated) {
                continue;
            }

            $this->snapshotBuilder->build_for_event($eventId);
            $processed++;
        }
    }

    /** @param array<string, mixed> $event */
    private function should_complete_event(array $event): bool
    {
        $endRaw = (string) ($event['end_at'] ?? '');
        if ($endRaw === '') {
            return false;
        }

        $timezone = $this->resolve_timezone();
        $endAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endRaw, $timezone);
        if (!$endAt) {
            return false;
        }

        $now = new \DateTimeImmutable('now', $timezone);
        return $endAt < $now;
    }

    private function resolve_timezone(): \DateTimeZone
    {
        return new \DateTimeZone('Europe/Amsterdam');
    }

    /** @param array<string, mixed> $event */
    private function get_end_at_sort_key(array $event): int
    {
        $endRaw = (string) ($event['end_at'] ?? '');
        if ($endRaw === '') {
            return PHP_INT_MAX;
        }

        $timezone = $this->resolve_timezone();
        $endAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endRaw, $timezone);
        if (!$endAt) {
            return PHP_INT_MAX;
        }

        return $endAt->getTimestamp();
    }

    /** @param array<string, array<string, int>> $schedules */
    public static function register_schedule(array $schedules): array
    {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display' => 'Cada 15 minutos',
            ];
        }

        return $schedules;
    }
}
