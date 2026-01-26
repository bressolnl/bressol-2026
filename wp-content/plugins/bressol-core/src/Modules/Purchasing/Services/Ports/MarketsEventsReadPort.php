<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface MarketsEventsReadPort
{
    /** @return array<int, array<string, mixed>> */
    public function get_upcoming_events(\DateTimeImmutable $fromUtc, \DateTimeImmutable $toUtc): array;
}
