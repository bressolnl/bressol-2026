<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;
use Bressol\Modules\Pos\Services\PosMarketProviderInterface;
use Bressol\Modules\MarketsEvents\Services\PosEventEligibilityService;

if (!defined('ABSPATH')) {
    exit;
}

final class MarketsEventsPosMarketProvider implements PosMarketProviderInterface
{
    private EventRepository $repository;
    private PosEventEligibilityService $eligibilityService;

    public function __construct(?EventRepository $repository = null, ?PosEventEligibilityService $eligibilityService = null)
    {
        $this->repository = $repository ?? new EventRepository();
        $this->eligibilityService = $eligibilityService ?? new PosEventEligibilityService($this->repository);
    }

    public function get_markets_for_pos(): array
    {
        $events = $this->eligibilityService->list_eligible_events(200);

        $markets = [];
        foreach ($events as $event) {
            $id = isset($event['id']) ? (int) $event['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $title = trim((string) ($event['title'] ?? ''));
            $location = trim((string) ($event['location_name'] ?? ''));
            $name = $title;
            if ($location !== '') {
                $name = $title !== '' ? $title . ' - ' . $location : $location;
            }

            $markets[] = [
                'id' => 'event:' . $id,
                'name' => $name !== '' ? $name : ('Event ' . $id),
                'default_cost_cents' => 0,
            ];
        }

        return $markets;
    }
}
