<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class IdempotencyStore
{
    public function was_done(string $key): bool
    {
        return get_option($key, '') !== '';
    }

    public function mark_done(string $key): void
    {
        update_option($key, (string) time(), false);
    }
}
