<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\NullAdapters;

use Bressol\Modules\Purchasing\Services\Ports\MarketsEventsReadPort;

if (!defined('ABSPATH')) {
    exit;
}

final class NullMarketsEventsReadPort implements MarketsEventsReadPort
{
    public function get_upcoming_events(\DateTimeImmutable $fromUtc, \DateTimeImmutable $toUtc): array
    {
        return [];
    }
}
