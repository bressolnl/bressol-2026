<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Repositories\ReceivingRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ReceivingService
{
    private ReceivingRepository $repository;

    public function __construct(ReceivingRepository $repository)
    {
        $this->repository = $repository;
    }

    /** @return array<int, array<string, mixed>> */
    public function list_receivings(): array
    {
        return $this->repository->list();
    }
}
