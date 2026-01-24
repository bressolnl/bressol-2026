<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

use Bressol\Modules\Pos\Repositories\OpenedItemsRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class OpenedItemsService
{
    private OpenedItemsRepository $repository;

    public function __construct(?OpenedItemsRepository $repository = null)
    {
        $this->repository = $repository ?? new OpenedItemsRepository();
    }

    public function create_opened_item(
        int $eventId,
        int $productId,
        int $userId,
        int $qty,
        int $internalOrderId
    ): int {
        if ($eventId <= 0) {
            throw new \InvalidArgumentException('event_id must be > 0');
        }
        if ($productId <= 0) {
            throw new \InvalidArgumentException('product_id must be > 0');
        }
        if ($userId <= 0) {
            throw new \InvalidArgumentException('user_id must be > 0');
        }
        if ($qty <= 0) {
            throw new \InvalidArgumentException('qty must be > 0');
        }
        if ($internalOrderId <= 0) {
            throw new \InvalidArgumentException('internal_order_id must be > 0');
        }

        $openedAt = current_time('mysql');
        $data = [
            'product_id' => $productId,
            'opened_at' => $openedAt,
            'opened_event_id' => $eventId,
            'opened_by_user_id' => $userId,
            'initial_qty' => $qty,
            'internal_order_id' => $internalOrderId,
            'status' => 'open',
            'discarded_at' => null,
            'discard_reason' => null,
        ];

        $id = $this->repository->insert_opened_item($data);
        if ($id <= 0) {
            throw new \RuntimeException('failed to create opened item');
        }

        return $id;
    }

    /** @return array<int, array<string, mixed>> */
    public function list_open_items(int $limit = 50, int $page = 1): array
    {
        $limit = max(1, min(200, $limit));
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        return $this->repository->list_open_items($limit, $offset);
    }

    /** @param int[] $ids */
    public function discard_opened_items(array $ids, string $reason): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static function (int $id): bool {
            return $id > 0;
        }));
        if ($ids === []) {
            throw new \InvalidArgumentException('opened_item_ids required');
        }

        $reason = sanitize_text_field($reason);
        $reason = substr($reason, 0, 255);
        if ($reason === '') {
            throw new \InvalidArgumentException('discard_reason required');
        }

        $discardedAt = current_time('mysql');
        return $this->repository->discard_items($ids, $reason, $discardedAt);
    }
}
