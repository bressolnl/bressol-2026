<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class ReceivingRepository
{
    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        return [];
    }
}
