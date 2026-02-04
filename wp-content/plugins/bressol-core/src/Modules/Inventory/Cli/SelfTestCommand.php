<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Cli;

use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;
use Bressol\Modules\Inventory\Services\ExpiryAlertsService;
use Bressol\Modules\Inventory\Services\AuditLogger;
use Bressol\Modules\Inventory\Services\CacheService;
use Bressol\Modules\Inventory\Services\SellableService;
use Bressol\Modules\Inventory\Transfers\Services\TransferService;

if (!defined('ABSPATH')) {
    exit;
}

final class SelfTestCommand
{
    /**
     * Selftest de Inventory.
     *
     * ## OPTIONS
     *
     * [--pack_id=<id>]
     * : ID de pack a verificar.
     *
     * [--transfer_product_id=<id>]
     * : ID de producto para selftest de transferencias.
     *
     * [--transfer_qty=<qty>]
     * : Qty para selftest de transferencias (default 1).
     *
     * [--transfer_unit_cogs_cents=<cents>]
     * : COGS unitario para selftest (default 0).
     *
     * [--transfer_expiry_date=<YYYY-MM-DD>]
     * : Caducidad opcional para selftest de transferencias.
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        if (!class_exists('WooCommerce')) {
            \WP_CLI::error('WooCommerce no disponible.');
            return;
        }

        $cache = new CacheService();
        $audit = new AuditLogger();
        $service = SellableService::build_default($cache, $audit);

        $transferProductId = isset($assocArgs['transfer_product_id']) ? (int) $assocArgs['transfer_product_id'] : 0;
        if ($transferProductId > 0) {
            $this->run_transfer_selftest($transferProductId, $assocArgs);
            return;
        }

        $packId = isset($assocArgs['pack_id']) ? (int) $assocArgs['pack_id'] : 0;
        if ($packId > 0) {
            $result = $service->get_pack_sellable($packId);
            \WP_CLI::log('Pack #' . $packId . ' sellable=' . $result['sellable'] . ' bottleneck=' . $result['bottleneck_product_id']);
            return;
        }

        $packs = $service->list_packs('', 5);
        if ($packs === []) {
            \WP_CLI::warning('No hay packs para selftest.');
            return;
        }

        foreach ($packs as $pack) {
            $id = (int) ($pack['product_id'] ?? 0);
            $sellable = (int) ($pack['sellable'] ?? 0);
            $bottleneck = (int) ($pack['bottleneck_product_id'] ?? 0);
            \WP_CLI::log('Pack #' . $id . ' sellable=' . $sellable . ' bottleneck=' . $bottleneck);
        }
    }

    private function run_transfer_selftest(int $productId, array $assocArgs): void
    {
        $qty = isset($assocArgs['transfer_qty']) ? (int) $assocArgs['transfer_qty'] : 1;
        $unitCogs = isset($assocArgs['transfer_unit_cogs_cents']) ? (int) $assocArgs['transfer_unit_cogs_cents'] : 0;
        $expiry = isset($assocArgs['transfer_expiry_date']) ? (string) $assocArgs['transfer_expiry_date'] : null;

        if ($productId <= 0 || $qty <= 0 || $unitCogs < 0) {
            \WP_CLI::error('Parámetros inválidos para selftest de transferencias.');
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
            \WP_CLI::error('Failed to create ES lot for selftest.');
        }

        $transferService = new TransferService();
        $transferId = $transferService->create_transfer([[
            'product_id' => $productId,
            'qty_units' => $qty,
            'expiry_date' => $expiry,
            'unit_cogs_cents' => $unitCogs,
        ]], get_current_user_id(), 'Selftest transfer');

        $transferService->ship($transferId);
        try {
            $transferService->ship($transferId);
            \WP_CLI::error('Ship retry did not fail.');
        } catch (\RuntimeException $exception) {
            \WP_CLI::log('Ship idempotencia OK: ' . $exception->getMessage());
        }

        $transferService->receive($transferId);
        try {
            $transferService->receive($transferId);
            \WP_CLI::error('Receive retry did not fail.');
        } catch (\RuntimeException $exception) {
            \WP_CLI::log('Receive idempotencia OK: ' . $exception->getMessage());
        }

        try {
            (new ExpiryAlertsService())->refresh_cache();
            \WP_CLI::log('Expiry alerts refresh_cache OK.');
        } catch (\Throwable $exception) {
            \WP_CLI::error('Expiry alerts refresh_cache failed: ' . $exception->getMessage());
        }

        \WP_CLI::success('Selftest transfer completado: ' . $transferId);
    }
}
