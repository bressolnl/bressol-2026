<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;
use Bressol\Modules\MarketsEvents\Services\PosEventEligibilityService;

if (!defined('ABSPATH')) {
    exit;
}

final class PosMarketResolver
{
    private PosSettings $settings;
    private EventRepository $eventRepository;
    private PosEventEligibilityService $eligibilityService;

    public function __construct(
        ?PosSettings $settings = null,
        ?EventRepository $eventRepository = null,
        ?PosEventEligibilityService $eligibilityService = null
    )
    {
        $this->settings = $settings ?? new PosSettings();
        $this->eventRepository = $eventRepository ?? new EventRepository();
        $this->eligibilityService = $eligibilityService ?? new PosEventEligibilityService($this->eventRepository);
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

        if (!$this->eligibilityService->is_event_eligible_for_pos($event)) {
            throw new \InvalidArgumentException('event not eligible');
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
