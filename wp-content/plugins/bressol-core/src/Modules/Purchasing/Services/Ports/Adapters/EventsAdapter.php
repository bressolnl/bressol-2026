<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\Adapters;

use Bressol\Modules\Purchasing\Services\Ports\EventsPort;
use Bressol\Modules\MarketsEvents\Repositories\EventRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class EventsAdapter implements EventsPort
{
    public function is_available(): bool
    {
        return class_exists(EventRepository::class);
    }

    public function get_events(string $windowStartUtc, string $windowEndUtc): array
    {
        if (!$this->is_available()) {
            return [];
        }

        $repository = new EventRepository();
        $events = $repository->find_by_filters([
            'status' => 'confirmed',
            'date_from' => $windowStartUtc,
            'date_to' => $windowEndUtc,
        ], 50, 1);

        $payload = [];
        foreach ($events as $event) {
            $startAt = (string) ($event['start_at'] ?? '');
            $timezone = (string) ($event['timezone'] ?? '');
            $payload[] = [
                'event_id' => (int) ($event['id'] ?? 0),
                'type' => (string) ($event['type'] ?? ''),
                'date_utc' => $this->to_utc($startAt, $timezone),
                'expected_weight' => 1.0,
            ];
        }

        return $payload;
    }

    private function to_utc(string $datetime, string $timezone): string
    {
        if ($datetime === '') {
            return '';
        }

        $tz = $timezone !== '' ? $timezone : 'UTC';
        try {
            $sourceTz = new \DateTimeZone($tz);
        } catch (\Throwable $exception) {
            $sourceTz = new \DateTimeZone('UTC');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime, $sourceTz);
        if (!$date) {
            return '';
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
