<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\NullAdapters;

use Bressol\Modules\Purchasing\Services\Ports\EventsPort;

if (!defined('ABSPATH')) {
    exit;
}

final class NullEventsPort implements EventsPort
{
    public function is_available(): bool
    {
        return false;
    }

    public function get_events(string $windowStartUtc, string $windowEndUtc): array
    {
        return [];
    }
}
