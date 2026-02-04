<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

use Bressol\Modules\Pos\Repositories\BundlePicksRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class BundlePicksService
{
    private BundlePicksRepository $repository;

    public function __construct(?BundlePicksRepository $repository = null)
    {
        $this->repository = $repository ?? new BundlePicksRepository();
    }

    /**
     * @param array<int, array{product_id:int,sku:string,qty:int}> $picks
     */
    public function record_picks(
        int $saleId,
        int $eventId,
        string $parentLineKey,
        array $picks
    ): int {
        if ($saleId <= 0) {
            throw new \InvalidArgumentException('sale_id must be > 0');
        }
        if ($parentLineKey === '') {
            throw new \InvalidArgumentException('parent_line_key required');
        }
        if ($picks === []) {
            throw new \InvalidArgumentException('bundle picks required');
        }

        $parentLineKey = substr(sanitize_text_field($parentLineKey), 0, 64);
        $createdAt = current_time('mysql');
        $inserted = 0;

        foreach ($picks as $pick) {
            if (!is_array($pick)) {
                continue;
            }
            $productId = isset($pick['product_id']) ? (int) $pick['product_id'] : 0;
            $qty = isset($pick['qty']) ? (int) $pick['qty'] : 0;
            $sku = isset($pick['sku']) ? (string) $pick['sku'] : '';
            if ($productId <= 0 || $qty <= 0 || $sku === '') {
                continue;
            }

            $payload = [
                'sale_id' => $saleId,
                'parent_line_key' => $parentLineKey,
                'picked_product_id' => $productId,
                'picked_sku' => substr(sanitize_text_field($sku), 0, 64),
                'qty' => $qty,
                'event_id' => $eventId,
                'created_at' => $createdAt,
            ];

            if ($this->repository->insert_pick($payload)) {
                $inserted++;
            }
        }

        return $inserted;
    }
}
