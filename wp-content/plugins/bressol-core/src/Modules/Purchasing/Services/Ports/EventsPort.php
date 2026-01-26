<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface EventsPort
{
    public function is_available(): bool;

    /** @return array<int, array<string, mixed>> */
    public function get_events(string $windowStartUtc, string $windowEndUtc): array;
}
