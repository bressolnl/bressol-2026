<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class PosMarketResolver
{
    private PosSettings $settings;
    private EventRepository $eventRepository;

    public function __construct(?PosSettings $settings = null, ?EventRepository $eventRepository = null)
    {
        $this->settings = $settings ?? new PosSettings();
        $this->eventRepository = $eventRepository ?? new EventRepository();
    }

    /** @return array{kind:'event', market_id:string, market_name:string, event_id:int} */
    public function resolve(string $marketId): array
    {
        $marketId = trim($marketId);
        if ($marketId === '' || preg_match('/^event:(\d+)$/', $marketId, $matches) !== 1) {
            throw new \InvalidArgumentException('market_id invalid');
        }

        $eventId = (int) $matches[1];
        if ($eventId <= 0) {
            throw new \InvalidArgumentException('event_id invalid');
        }

        $event = $this->eventRepository->find_by_id($eventId);
        if (!$event) {
            throw new \InvalidArgumentException('event not found');
        }

        $status = (string) ($event['status'] ?? '');
        $channels = (string) ($event['channels'] ?? '');
        if ($status !== 'confirmed' || !in_array($channels, ['pos', 'both'], true)) {
            throw new \InvalidArgumentException('event not allowed');
        }

        $title = trim((string) ($event['title'] ?? ''));
        $location = trim((string) ($event['location_name'] ?? ''));
        $marketName = $title;
        if ($location !== '') {
            $marketName = $title !== '' ? $title . ' - ' . $location : $location;
        }
        if ($marketName === '') {
            $marketName = 'Event ' . $eventId;
        }

        return [
            'kind' => 'event',
            'market_id' => $marketId,
            'market_name' => $marketName,
            'event_id' => $eventId,
        ];
    }
}
