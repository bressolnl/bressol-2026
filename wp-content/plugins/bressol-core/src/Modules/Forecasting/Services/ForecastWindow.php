<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class ForecastWindow
{
    private const WEEKS = 6;

    /** @return array{from_utc:string,to_utc:string,weeks:array<int, array{key:string,start_utc:string,end_utc:string}>} */
    public function get_window(?\DateTimeImmutable $nowUtc = null): array
    {
        $nowUtc = $nowUtc ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $from = $nowUtc->setTime(0, 0, 0);
        $to = $from->modify('+' . (self::WEEKS * 7 - 1) . ' days')->setTime(23, 59, 59);

        return [
            'from_utc' => $from->format('Y-m-d H:i:s'),
            'to_utc' => $to->format('Y-m-d H:i:s'),
            'weeks' => $this->build_weeks($from),
        ];
    }

    /** @param array<int, array{key:string,start_utc:string,end_utc:string}> $weeks */
    public function resolve_week_key(\DateTimeImmutable $dateUtc, array $weeks): ?string
    {
        $timestamp = $dateUtc->getTimestamp();
        foreach ($weeks as $week) {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $week['start_utc'], new \DateTimeZone('UTC'));
            $end = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $week['end_utc'], new \DateTimeZone('UTC'));
            if (!$start || !$end) {
                continue;
            }
            if ($timestamp >= $start->getTimestamp() && $timestamp <= $end->getTimestamp()) {
                return $week['key'];
            }
        }

        return null;
    }

    /** @return array<int, array{key:string,start_utc:string,end_utc:string}> */
    private function build_weeks(\DateTimeImmutable $fromUtc): array
    {
        $weeks = [];
        for ($i = 0; $i < self::WEEKS; $i++) {
            $start = $fromUtc->modify('+' . ($i * 7) . ' days');
            $end = $start->modify('+6 days')->setTime(23, 59, 59);
            $weeks[] = [
                'key' => $start->format('Y-m-d'),
                'start_utc' => $start->format('Y-m-d H:i:s'),
                'end_utc' => $end->format('Y-m-d H:i:s'),
            ];
        }

        return $weeks;
    }
}
