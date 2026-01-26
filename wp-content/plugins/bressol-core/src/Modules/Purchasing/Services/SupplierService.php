<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

use Bressol\Modules\Purchasing\Repositories\SupplierRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class SupplierService
{
    private SupplierRepository $repository;

    public function __construct(SupplierRepository $repository)
    {
        $this->repository = $repository;
    }

    /** @return array<int, array<string, mixed>> */
    public function list_suppliers(): array
    {
        return $this->repository->list();
    }
}
