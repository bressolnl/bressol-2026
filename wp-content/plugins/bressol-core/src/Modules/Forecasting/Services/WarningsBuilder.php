<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class WarningsBuilder
{
    /** @param array<string, mixed> $context
     *  @return string[]
     */
    public function build(array $context): array
    {
        $warnings = [];

        $eventsCount = (int) ($context['events_count'] ?? 0);
        if ($eventsCount <= 0) {
            $warnings[] = 'NO_EVENTS: No hay eventos futuros en la ventana.';
        }

        if (!empty($context['events_fallback_used'])) {
            $warnings[] = 'EVENT_FALLBACK: Usando status=planned por falta de confirmed.';
        }

        $missingSnapshots = $context['missing_snapshots'] ?? [];
        if (is_array($missingSnapshots) && $missingSnapshots !== []) {
            $warnings[] = 'MISSING_SNAPSHOTS: Eventos sin snapshot: ' . implode(', ', array_map('intval', $missingSnapshots));
        }

        $emptySnapshots = $context['empty_snapshots'] ?? [];
        if (is_array($emptySnapshots) && $emptySnapshots !== []) {
            $warnings[] = 'EMPTY_SNAPSHOTS: Snapshots sin líneas: ' . implode(', ', array_map('intval', $emptySnapshots));
        }

        $unknownStock = $context['unknown_stock'] ?? [];
        if (is_array($unknownStock) && $unknownStock !== []) {
            $warnings[] = 'UNKNOWN_STOCK: Stock no gestionado para productos: ' . implode(', ', array_map('intval', $unknownStock));
        }

        if (!empty($context['inventory_fallback_used'])) {
            $warnings[] = 'INVENTORY_FALLBACK_USED: Stock obtenido desde WooCommerce.';
        }

        if (!empty($context['pack_no_components'])) {
            $warnings[] = 'PACK_NO_COMPONENTS: Packs sin componentes definidos.';
        }

        if (!empty($context['order_meta_key_not_found'])) {
            $warnings[] = 'ORDER_META_KEY_NOT_FOUND: Meta key evento no detectada.';
        }

        if (!empty($context['refunds_applied'])) {
            $warnings[] = 'REFUNDS_APPLIED: Devoluciones aplicadas al neto.';
        }

        return $warnings;
    }
}
