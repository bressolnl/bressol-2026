<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Cli;

use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;
use Bressol\Modules\Inventory\Transfers\Services\TransferService;

if (!defined('ABSPATH')) {
    exit;
}

final class TransferDemoCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $productId = isset($assocArgs['product_id']) ? (int) $assocArgs['product_id'] : 0;
        $qty = isset($assocArgs['qty']) ? (int) $assocArgs['qty'] : 10;
        $unitCogs = isset($assocArgs['unit_cogs_cents']) ? (int) $assocArgs['unit_cogs_cents'] : 0;
        $expiry = isset($assocArgs['expiry_date']) ? (string) $assocArgs['expiry_date'] : null;

        if ($productId <= 0) {
            \WP_CLI::error('Missing --product_id.');
        }
        if ($qty <= 0) {
            \WP_CLI::error('Invalid --qty.');
        }
        if ($unitCogs < 0) {
            \WP_CLI::error('Invalid --unit_cogs_cents.');
        }

        $lotRepo = new LotRepository();
        $lotId = $lotRepo->create_lot([
            'product_id' => $productId,
            'location' => 'ES',
            'qty_on_hand' => $qty,
            'expiry_date' => $expiry,
            'unit_cogs_cents' => $unitCogs,
            'unit_weight_grams' => 0,
            'source' => 'manual',
        ]);

        if ($lotId <= 0) {
            \WP_CLI::error('Failed to create ES lot.');
        }

        $service = new TransferService();
        $transferId = $service->create_transfer([[
            'product_id' => $productId,
            'qty_units' => $qty,
            'expiry_date' => $expiry,
            'unit_cogs_cents' => $unitCogs,
        ]], get_current_user_id(), 'Demo transfer');

        $service->ship($transferId);
        $service->receive($transferId);

        \WP_CLI::success('Transfer completed: ' . $transferId);
    }
}
