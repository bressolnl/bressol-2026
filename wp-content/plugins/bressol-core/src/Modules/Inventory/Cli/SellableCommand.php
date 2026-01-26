<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Cli;

use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;
use Bressol\Modules\Inventory\Lots\Services\LotService;

if (!defined('ABSPATH')) {
    exit;
}

final class SellableCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $productId = isset($assocArgs['product_id']) ? (int) $assocArgs['product_id'] : 0;
        $location = isset($assocArgs['location']) ? strtoupper((string) $assocArgs['location']) : 'NL';

        if ($productId <= 0) {
            \WP_CLI::error('Missing --product_id.');
        }

        if (!in_array($location, ['NL', 'ES'], true)) {
            \WP_CLI::error('Invalid --location (use NL or ES).');
        }

        $today = current_time('Y-m-d');
        $excludeExpired = $location === 'NL';
        $service = new LotService();
        $available = $service->get_available_qty($productId, $location, $excludeExpired, $today);

        if ($location === 'NL') {
            $repo = new LotRepository();
            $lots = $repo->get_lots_for_product_location($productId, $location);
            $expiredQty = 0;
            foreach ($lots as $lot) {
                $qty = (int) ($lot['qty_on_hand'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $expiry = (string) ($lot['expiry_date'] ?? '');
                if ($expiry !== '' && $expiry < $today) {
                    $expiredQty += $qty;
                }
            }

            \WP_CLI::success('Available qty (NL): ' . $available . ' | Expired qty: ' . $expiredQty);
            return;
        }

        \WP_CLI::success('Available qty (ES): ' . $available);
    }
}
