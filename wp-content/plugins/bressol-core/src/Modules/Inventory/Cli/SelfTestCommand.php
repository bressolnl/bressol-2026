<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Cli;

use Bressol\Modules\Inventory\Services\AuditLogger;
use Bressol\Modules\Inventory\Services\CacheService;
use Bressol\Modules\Inventory\Services\SellableService;

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
}
