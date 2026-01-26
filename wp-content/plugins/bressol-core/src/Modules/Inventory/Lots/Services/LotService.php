<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Lots\Services;

use Bressol\Modules\Inventory\Lots\Repositories\LotMoveRepository;
use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;
use DateTimeImmutable;

if (!defined('ABSPATH')) {
    exit;
}

final class LotService
{
    private LotRepository $lotRepository;
    private LotMoveRepository $lotMoveRepository;

    public function __construct(?LotRepository $lotRepository = null, ?LotMoveRepository $lotMoveRepository = null)
    {
        $this->lotRepository = $lotRepository ?? new LotRepository();
        $this->lotMoveRepository = $lotMoveRepository ?? new LotMoveRepository();
    }

    /** @param array<string, mixed> $data */
    public function create_lot(array $data): int
    {
        return $this->lotRepository->create_lot($data);
    }

    public function get_available_qty(int $productId, string $location, bool $excludeExpired = true, ?string $atDate = null): int
    {
        return $this->lotRepository->get_available_qty($productId, $location, $excludeExpired, $atDate);
    }

    /**
     * @return array<int, array{lot_id:int,qty:int,expiry_date:?string,unit_cogs_cents:int}>
     */
    public function allocate_fifo(int $productId, string $location, int $qty, bool $excludeExpired = true, ?string $atDate = null): array
    {
        if ($productId <= 0 || $qty <= 0) {
            return [];
        }

        $location = strtoupper($location);
        $lots = $this->lotRepository->get_lots_for_product_location($productId, $location);
        if ($lots === []) {
            return [];
        }

        $remaining = $qty;
        $allocations = [];
        $cutoff = $this->normalize_date($atDate ?? current_time('Y-m-d'));

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $available = (int) ($lot['qty_on_hand'] ?? 0);
            if ($available <= 0) {
                continue;
            }

            if ($excludeExpired && $cutoff !== null) {
                $expiry = $this->normalize_date($lot['expiry_date'] ?? null);
                if ($expiry !== null && $expiry < $cutoff) {
                    continue;
                }
            }

            $take = min($available, $remaining);
            if ($take <= 0) {
                continue;
            }

            $allocations[] = [
                'lot_id' => (int) ($lot['id'] ?? 0),
                'qty' => $take,
                'expiry_date' => $lot['expiry_date'] ?? null,
                'unit_cogs_cents' => (int) ($lot['unit_cogs_cents'] ?? 0),
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            return [];
        }

        return $allocations;
    }

    public function decrement_lot(int $lotId, int $qty, string $refType, string $refId, ?string $note = null): void
    {
        if ($lotId <= 0 || $qty <= 0) {
            throw new \RuntimeException('Invalid lot decrement.');
        }

        $updated = $this->lotRepository->increment_qty($lotId, -$qty);
        if (!$updated) {
            throw new \RuntimeException('Insufficient lot stock.');
        }

        $this->lotMoveRepository->add_move($lotId, 'consume', -$qty, $refType, $refId, $note);
    }

    public function increment_lot(int $lotId, int $qty, string $refType, string $refId, ?string $note = null): void
    {
        if ($lotId <= 0 || $qty <= 0) {
            throw new \RuntimeException('Invalid lot increment.');
        }

        $updated = $this->lotRepository->increment_qty($lotId, $qty);
        if (!$updated) {
            throw new \RuntimeException('Failed to update lot.');
        }

        $this->lotMoveRepository->add_move($lotId, 'receipt', $qty, $refType, $refId, $note);
    }

    private function normalize_date($value): ?string
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d');
        }

        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($date && $date->format('Y-m-d') === $raw) {
            return $raw;
        }

        return null;
    }
}
