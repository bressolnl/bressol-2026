<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class FiltersNormalizer
{
    /** @param array<string, mixed> $filters
     *  @return array<string, string>
     */
    public static function normalize(array $filters): array
    {
        $dateFrom = isset($filters['date_from']) ? (string) $filters['date_from'] : '';
        $dateTo = isset($filters['date_to']) ? (string) $filters['date_to'] : '';
        $channel = isset($filters['channel']) ? (string) $filters['channel'] : 'all';
        $marketId = isset($filters['market_id']) ? (string) $filters['market_id'] : '';

        $normalized = [
            'date_from' => self::normalize_date($dateFrom),
            'date_to' => self::normalize_date($dateTo),
            'channel' => self::normalize_channel($channel),
            'market_id' => $marketId,
            'status' => 'completed',
        ];

        return $normalized;
    }

    private static function normalize_date(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    private static function normalize_channel(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || $value === 'all') {
            return 'all';
        }

        return in_array($value, ['web', 'pos'], true) ? $value : 'all';
    }
}
