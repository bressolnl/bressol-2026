<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;
use Bressol\Modules\MarketsEvents\Services\PosEventEligibilityService;

if (!defined('ABSPATH')) {
    exit;
}

final class PosEventContextService
{
    public const USER_META_KEY = 'bressol_pos_active_event_id';
    public const OPTION_KEY = 'bressol_pos_active_event_id_global';
    private const USER_META_DATE_KEY = 'bressol_pos_active_event_date';

    private EventRepository $repository;
    private PosEventEligibilityService $eligibilityService;

    public function __construct(?EventRepository $repository = null, ?PosEventEligibilityService $eligibilityService = null)
    {
        $this->repository = $repository ?? new EventRepository();
        $this->eligibilityService = $eligibilityService ?? new PosEventEligibilityService($this->repository);
    }

    public function get_active_event_id(int $userId = 0): ?int
    {
        $userId = $userId > 0 ? $userId : get_current_user_id();
        if ($userId > 0) {
            $value = get_user_meta($userId, self::USER_META_KEY, true);
            $eventId = is_numeric($value) ? (int) $value : 0;
            if ($eventId > 0 && $this->is_event_allowed_for_pos($eventId)) {
                return $eventId;
            }
        }

        $global = get_option(self::OPTION_KEY, '');
        $globalId = is_numeric($global) ? (int) $global : 0;
        if ($globalId > 0 && $this->is_event_allowed_for_pos($globalId)) {
            return $globalId;
        }

        return $this->resolve_default_event_id();
    }

    public function get_active_event_id_for_today(int $userId, string $today): ?int
    {
        $today = trim($today);
        if ($today === '') {
            return null;
        }

        $eventId = $this->get_active_event_id($userId);
        if (!$eventId) {
            return null;
        }

        $date = (string) get_user_meta($userId, self::USER_META_DATE_KEY, true);
        if ($date !== $today) {
            return null;
        }

        return $eventId;
    }

    public function set_active_event_for_today(int $userId, int $eventId, string $today): void
    {
        if ($userId <= 0) {
            return;
        }
        $today = trim($today);
        if ($eventId <= 0 || $today === '') {
            $this->clear_active_event($userId);
            return;
        }

        $this->set_active_event_id($eventId, $userId);
        update_user_meta($userId, self::USER_META_DATE_KEY, $today);
    }

    public function clear_active_event(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        delete_user_meta($userId, self::USER_META_KEY);
        delete_user_meta($userId, self::USER_META_DATE_KEY);
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
        $events = $this->eligibilityService->list_eligible_events(1);
        if (!empty($events[0]['id'])) {
            return (int) $events[0]['id'];
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
        return $this->eligibilityService->is_event_eligible_for_pos($event);
    }
}
