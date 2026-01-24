<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

use Bressol\Modules\MarketsEvents\Repositories\EventRepository;
use Bressol\Modules\Pos\Services\PosMarketProviderInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class MarketsEventsPosMarketProvider implements PosMarketProviderInterface
{
    private EventRepository $repository;

    public function __construct(?EventRepository $repository = null)
    {
        $this->repository = $repository ?? new EventRepository();
    }

    public function get_markets_for_pos(): array
    {
        $events = $this->repository->find_by_filters([
            'status' => 'confirmed',
        ], 200, 1);

        $markets = [];
        foreach ($events as $event) {
            $id = isset($event['id']) ? (int) $event['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $channels = (string) ($event['channels'] ?? '');
            if (!in_array($channels, ['pos', 'both'], true)) {
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
