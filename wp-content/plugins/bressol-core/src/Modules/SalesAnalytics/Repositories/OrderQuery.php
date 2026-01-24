<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderQuery
{
    /** @param array<string, mixed> $filters
     *  @return int[]
     */
    public function find_order_ids(array $filters, int $limit, int $page): array
    {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $args = [
            'status' => 'completed',
            'return' => 'ids',
            'limit' => max(1, $limit),
            'paged' => max(1, $page),
        ];

        $dateArgs = $this->build_date_filter($filters);
        if ($dateArgs !== null) {
            $args['date_created'] = $dateArgs;
        }

        $channel = isset($filters['channel']) ? (string) $filters['channel'] : 'all';
        $marketId = isset($filters['market_id']) ? (string) $filters['market_id'] : '';

        $metaQuery = [];
        if ($channel === 'pos') {
            $metaQuery[] = [
                'key' => '_bressol_pos_channel',
                'value' => 'pos',
                'compare' => '=',
            ];
            if ($marketId !== '') {
                $metaQuery[] = [
                    'key' => '_bressol_pos_market_id',
                    'value' => $marketId,
                    'compare' => '=',
                ];
            }
        } elseif ($channel === 'web') {
            // Web = pedidos sin meta POS o con valor distinto a "pos".
            $metaQuery = [
                'relation' => 'OR',
                [
                    'key' => '_bressol_pos_channel',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_bressol_pos_channel',
                    'value' => 'pos',
                    'compare' => '!=',
                ],
            ];
        }

        if ($metaQuery !== []) {
            $args['meta_query'] = $metaQuery;
        }

        $orders = wc_get_orders($args);
        $ids = [];
        foreach ($orders as $orderId) {
            $ids[] = (int) $orderId;
        }

        return $ids;
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>|null
     */
    private function build_date_filter(array $filters): ?array
    {
        $from = isset($filters['date_from']) ? (string) $filters['date_from'] : '';
        $to = isset($filters['date_to']) ? (string) $filters['date_to'] : '';

        if ($from === '' && $to === '') {
            return null;
        }

        return [
            'after' => $from !== '' ? $from . ' 00:00:00' : null,
            'before' => $to !== '' ? $to . ' 23:59:59' : null,
            'inclusive' => true,
        ];
    }
}
