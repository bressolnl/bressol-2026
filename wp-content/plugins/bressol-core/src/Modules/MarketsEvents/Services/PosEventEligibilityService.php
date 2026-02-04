<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class PosEventEligibilityService
{
    private const MAX_PAGES = 10;
    private const TZ_NAME = 'Europe/Amsterdam';
    /** @var string[] */
    private const POS_ALLOWED_STATUSES = ['planned', 'confirmed', 'paid', 'completed', 'done'];
    /** @var string[] */
    private const POS_BLOCKED_STATUSES = ['cancelled', 'archived'];

    private EventRepository $repository;

    public function __construct(?EventRepository $repository = null)
    {
        $this->repository = $repository ?? new EventRepository();
    }

    /** @return array<int, array<string, mixed>> */
    public function list_eligible_events(int $limit = 200): array
    {
        $limit = max(1, $limit);
        $events = $this->fetch_events_by_statuses(self::POS_ALLOWED_STATUSES, $limit);
        if ($events === []) {
            return [];
        }

        $now = new \DateTimeImmutable('now', $this->resolve_timezone());
        $eligible = [];
        foreach ($events as $event) {
            if ($this->is_event_eligible_for_pos($event, $now)) {
                $event['_sort_key'] = $this->compute_sort_key($event, $now);
                $eligible[] = $event;
            }
        }

        if ($eligible === []) {
            return [];
        }

        usort($eligible, static function (array $a, array $b): int {
            $aKey = $a['_sort_key'] ?? PHP_INT_MAX;
            $bKey = $b['_sort_key'] ?? PHP_INT_MAX;
            if ($aKey === $bKey) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            return $aKey <=> $bKey;
        });

        $eligible = array_slice($eligible, 0, $limit);
        foreach ($eligible as &$event) {
            unset($event['_sort_key']);
        }
        unset($event);

        return $eligible;
    }

    /**
     * @param array<string, mixed> $debugFiltered
     * @return array<int, array<string, mixed>>
     */
    public function list_events_today_with_debug(int $limit, array &$debugFiltered): array
    {
        $limit = max(1, $limit);
        $events = $this->fetch_events_by_statuses(self::POS_ALLOWED_STATUSES, $limit);
        if ($events === []) {
            return [];
        }

        $now = new \DateTimeImmutable('now', $this->resolve_timezone());
        $eligible = [];
        foreach ($events as $event) {
            $reason = $this->get_ineligible_reason($event, $now);
            if ($reason === null) {
                $event['_sort_key'] = $this->compute_sort_key($event, $now);
                $eligible[] = $event;
                continue;
            }
            $debugFiltered[] = [
                'id' => (int) ($event['id'] ?? 0),
                'status' => (string) ($event['status'] ?? ''),
                'reason' => $reason,
            ];
        }

        if ($eligible === []) {
            return [];
        }

        usort($eligible, static function (array $a, array $b): int {
            $aKey = $a['_sort_key'] ?? PHP_INT_MAX;
            $bKey = $b['_sort_key'] ?? PHP_INT_MAX;
            if ($aKey === $bKey) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            return $aKey <=> $bKey;
        });

        $eligible = array_slice($eligible, 0, $limit);
        foreach ($eligible as &$event) {
            unset($event['_sort_key']);
        }
        unset($event);

        return $eligible;
    }

    /** @param array<string, mixed> $event */
    public function is_event_eligible_for_pos(array $event, ?\DateTimeImmutable $now = null): bool
    {
        return $this->get_ineligible_reason($event, $now) === null;
    }

    /** @return array<int, array<string, mixed>> */
    private function fetch_events_by_statuses(array $statuses, int $limit): array
    {
        $events = [];
        $seen = [];

        foreach ($statuses as $status) {
            $page = 1;
            do {
                $rows = $this->repository->find_by_filters([
                    'status' => $status,
                ], $limit, $page);

                foreach ($rows as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    if ($id <= 0 || isset($seen[$id])) {
                        continue;
                    }
                    $events[] = $row;
                    $seen[$id] = true;
                }

                $page++;
            } while (count($rows) === $limit && $page <= self::MAX_PAGES);
        }

        return $events;
    }

    /** @param array<string, mixed> $event */
    private function compute_sort_key(array $event, \DateTimeImmutable $now): int
    {
        $startRaw = (string) ($event['start_at'] ?? '');
        $status = (string) ($event['status'] ?? '');
        $statusPriority = $this->resolve_status_priority($status);

        $tz = $this->resolve_timezone();
        $startAt = $startRaw !== ''
            ? \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startRaw, $tz)
            : null;
        if (!$startAt) {
            return ($statusPriority * 1000000000) + PHP_INT_MAX;
        }

        return ($statusPriority * 1000000000) + (int) $startAt->getTimestamp();
    }

    private function resolve_timezone(): \DateTimeZone
    {
        return new \DateTimeZone(self::TZ_NAME);
    }

    /** @param array<string, mixed> $event */
    private function get_ineligible_reason(array $event, ?\DateTimeImmutable $now): ?string
    {
        $status = (string) ($event['status'] ?? '');
        if (in_array($status, self::POS_BLOCKED_STATUSES, true)) {
            return 'status_blocked';
        }
        if (!in_array($status, self::POS_ALLOWED_STATUSES, true)) {
            return 'status_not_allowed';
        }

        $channels = strtolower(trim((string) ($event['channels'] ?? '')));
        if (in_array($channels, ['web', 'online', 'none'], true)) {
            return 'channel_not_pos';
        }

        $startRaw = (string) ($event['start_at'] ?? '');
        $endRaw = (string) ($event['end_at'] ?? '');
        if ($startRaw === '' || $endRaw === '') {
            return 'date_missing';
        }

        $tz = $this->resolve_timezone();
        $startAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startRaw, $tz);
        $endAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endRaw, $tz);
        if (!$startAt || !$endAt) {
            return 'date_invalid';
        }

        $today = ($now ?? new \DateTimeImmutable('now', $tz))->setTimezone($tz)->format('Y-m-d');
        $startDate = $startAt->format('Y-m-d');
        $endDate = $endAt->format('Y-m-d');
        if ($today < $startDate || $today > $endDate) {
            return 'date_outside';
        }

        return null;
    }

    private function resolve_status_priority(string $status): int
    {
        if (in_array($status, ['confirmed', 'paid'], true)) {
            return 0;
        }
        if ($status === 'planned') {
            return 1;
        }
        if (in_array($status, ['completed', 'done'], true)) {
            return 2;
        }
        return 3;
    }
}
