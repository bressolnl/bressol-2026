<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class PlanningStore
{
    private const LATEST_OPTION = 'bressol_purchasing_planning_latest';
    private const HISTORY_OPTION = 'bressol_purchasing_planning_history';
    private const HISTORY_LIMIT = 5;

    /** @return array<string, mixed>|null */
    public function get_latest(): ?array
    {
        $raw = get_option(self::LATEST_OPTION, '');
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function get_history(): array
    {
        $raw = get_option(self::HISTORY_OPTION, '');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $payload */
    public function save_run(array $payload): void
    {
        update_option(self::LATEST_OPTION, wp_json_encode($payload), false);

        $history = $this->get_history();
        array_unshift($history, $payload);
        $history = array_slice($history, 0, self::HISTORY_LIMIT);
        update_option(self::HISTORY_OPTION, wp_json_encode($history), false);
    }
}
