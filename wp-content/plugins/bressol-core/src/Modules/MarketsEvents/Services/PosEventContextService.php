<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class PosEventContextService
{
    public const USER_META_KEY = 'bressol_pos_active_event_id';
    public const OPTION_KEY = 'bressol_pos_active_event_id_global';

    private EventRepository $repository;

    public function __construct(?EventRepository $repository = null)
    {
        $this->repository = $repository ?? new EventRepository();
    }

    public function get_active_event_id(int $userId = 0): ?int
    {
        $userId = $userId > 0 ? $userId : get_current_user_id();
        if ($userId > 0) {
            $value = get_user_meta($userId, self::USER_META_KEY, true);
            $eventId = is_numeric($value) ? (int) $value : 0;
            if ($eventId > 0) {
                return $eventId;
            }
        }

        $global = get_option(self::OPTION_KEY, '');
        $globalId = is_numeric($global) ? (int) $global : 0;
        if ($globalId > 0) {
            return $globalId;
        }

        return $this->resolve_default_event_id();
    }

    public function set_active_event_id(?int $eventId, int $userId = 0): void
    {
        $userId = $userId > 0 ? $userId : get_current_user_id();
        $value = $eventId !== null ? max(0, (int) $eventId) : 0;

        if ($userId > 0) {
            if ($value > 0) {
                update_user_meta($userId, self::USER_META_KEY, $value);
            } else {
                delete_user_meta($userId, self::USER_META_KEY);
            }
            return;
        }

        if ($value > 0) {
            update_option(self::OPTION_KEY, $value, false);
        } else {
            delete_option(self::OPTION_KEY);
        }
    }

    public function resolve_default_event_id(): ?int
    {
        $now = new \DateTimeImmutable('now', wp_timezone());
        $events = $this->repository->find_by_filters([
            'status' => 'confirmed',
            'channel' => 'pos',
            'active_at' => $now,
        ], 1, 1);

        if (!empty($events[0]['id'])) {
            return (int) $events[0]['id'];
        }

        $upcoming = $this->repository->find_by_filters([
            'status' => 'confirmed',
            'channel' => 'pos',
            'date_from' => $now->format('Y-m-d H:i:s'),
        ], 1, 1);

        if (!empty($upcoming[0]['id'])) {
            return (int) $upcoming[0]['id'];
        }

        return null;
    }

    public function is_event_allowed_for_pos(int $eventId): bool
    {
        if ($eventId <= 0) {
            return false;
        }

        $event = $this->repository->find_by_id($eventId);
        if (!$event) {
            return false;
        }

        $status = (string) ($event['status'] ?? '');
        if ($status !== 'confirmed') {
            return false;
        }

        $channel = (string) ($event['channels'] ?? '');
        if (!in_array($channel, ['pos', 'both'], true)) {
            return false;
        }

        $start = isset($event['start_at']) ? (string) $event['start_at'] : '';
        $end = isset($event['end_at']) ? (string) $event['end_at'] : '';
        if ($start === '' || $end === '') {
            return false;
        }

        $startAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start, wp_timezone());
        $endAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end, wp_timezone());
        if (!$startAt || !$endAt) {
            return false;
        }

        $now = new \DateTimeImmutable('now', wp_timezone());
        return $now >= $startAt && $now <= $endAt;
    }
}
