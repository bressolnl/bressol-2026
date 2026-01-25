<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class PackDefinitionRepository
{
    public const META_KEY = '_bressol_pack_definition';

    /** @return array<string, mixed>|null */
    public function get_definition(int $productId): ?array
    {
        $raw = (string) get_post_meta($productId, self::META_KEY, true);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    public function is_pack_product(int $productId): bool
    {
        $raw = (string) get_post_meta($productId, self::META_KEY, true);
        return $raw !== '';
    }

    /** @return array<int, int> product_id => qty */
    public function normalize_selection_from_request(int $packProductId, array $input): array
    {
        $definition = $this->get_definition($packProductId);
        if (!$definition || empty($definition['slots']) || !is_array($definition['slots'])) {
            return [];
        }

        $requirements = [];

        foreach ($definition['slots'] as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $slotKey = (string) ($slot['key'] ?? '');
            if ($slotKey === '') {
                continue;
            }

            $max = (int) ($slot['max'] ?? 1);
            if ($max <= 1) {
                $rawVal = $input[$slotKey] ?? '';
                if ($rawVal === '' || $rawVal === null) {
                    continue;
                }

                $pid = 0;
                if (is_string($rawVal) && strpos($rawVal, '|') !== false) {
                    [$pidStr] = array_pad(explode('|', $rawVal), 1, '0');
                    $pid = (int) $pidStr;
                } else {
                    $pid = (int) $rawVal;
                }

                if ($pid > 0) {
                    $requirements[$pid] = ($requirements[$pid] ?? 0) + 1;
                }
                continue;
            }

            $slotValues = $input[$slotKey] ?? [];
            if (!is_array($slotValues)) {
                continue;
            }

            foreach ($slotValues as $pidStr => $qtyVal) {
                $pid = (int) $pidStr;
                $qty = (int) $qtyVal;
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $requirements[$pid] = ($requirements[$pid] ?? 0) + $qty;
            }
        }

        return $requirements;
    }

    /** @return array<int, int> product_id => qty */
    public function normalize_selection_from_cart_item(array $cartItem): array
    {
        $pack = $cartItem['bressol_pack'] ?? null;
        if (!is_array($pack)) {
            return [];
        }

        $selections = $pack['selections'] ?? null;
        if (!is_array($selections)) {
            return [];
        }

        $requirements = [];
        foreach ($selections as $lines) {
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $pid = isset($line['product_id']) ? (int) $line['product_id'] : 0;
                $qty = isset($line['qty']) ? (int) $line['qty'] : 0;
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $requirements[$pid] = ($requirements[$pid] ?? 0) + $qty;
            }
        }

        return $requirements;
    }
}
