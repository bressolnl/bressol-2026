<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderEventMetaService
{
    public const META_KEY = '_bressol_event_id';

    private PosEventContextService $contextService;

    public function __construct(?PosEventContextService $contextService = null)
    {
        $this->contextService = $contextService ?? new PosEventContextService();
    }

    public function set_order_event_id(int $orderId, ?int $eventId): void
    {
        if ($orderId <= 0) {
            return;
        }

        if ($eventId === null || $eventId <= 0) {
            delete_post_meta($orderId, self::META_KEY);
            return;
        }

        update_post_meta($orderId, self::META_KEY, $eventId);
    }

    public function get_order_event_id(int $orderId): ?int
    {
        if ($orderId <= 0) {
            return null;
        }

        $value = get_post_meta($orderId, self::META_KEY, true);
        $eventId = is_numeric($value) ? (int) $value : 0;

        return $eventId > 0 ? $eventId : null;
    }

    public function maybe_set_on_checkout_or_pos(int $orderId, int $userId = 0): void
    {
        if ($orderId <= 0) {
            return;
        }

        if ($this->get_order_event_id($orderId) !== null) {
            return;
        }

        $eventId = $this->contextService->get_active_event_id($userId);
        if ($eventId === null || $eventId <= 0) {
            return;
        }

        $this->set_order_event_id($orderId, $eventId);
    }
}
