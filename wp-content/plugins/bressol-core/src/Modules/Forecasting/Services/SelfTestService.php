<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services;

use Bressol\Modules\Forecasting\Installer;
use Bressol\Modules\Forecasting\Repositories\ForecastSnapshotRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class SelfTestService
{
    /** @return array<int, array{step:string,ok:bool,message:string}> */
    public function run(int $eventId, int $productId): array
    {
        $steps = [];
        $repo = new ForecastSnapshotRepository();

        try {
            if ($eventId <= 0 || $productId <= 0) {
                $steps[] = [
                    'step' => 'input',
                    'ok' => false,
                    'message' => 'Event ID y Product ID son obligatorios.',
                ];
                return $steps;
            }

            Installer::maybe_upgrade();
            $steps[] = [
                'step' => 'install',
                'ok' => true,
                'message' => 'Installer ejecutado.',
            ];

            $repo->delete_selftest_snapshots_for_event($eventId);
            $steps[] = [
                'step' => 'cleanup_pre',
                'ok' => true,
                'message' => 'Limpieza previa completada.',
            ];

            $snapshotId = $repo->create_snapshot(
                $eventId,
                'manual',
                gmdate('Y-m-d H:i:s'),
                get_current_user_id(),
                'selftest'
            );
            if ($snapshotId <= 0) {
                throw new \RuntimeException('No se pudo crear el snapshot.');
            }
            $steps[] = [
                'step' => 'snapshot_create',
                'ok' => true,
                'message' => 'Snapshot creado.',
            ];

            $repo->replace_lines($snapshotId, [
                ['product_id' => $productId, 'qty_units' => 1],
                ['product_id' => $productId + 1, 'qty_units' => 2],
            ]);
            $steps[] = [
                'step' => 'lines_insert',
                'ok' => true,
                'message' => 'Líneas insertadas.',
            ];

            $latest = $repo->get_latest_snapshot_for_event($eventId);
            if (!$latest || (int) ($latest['id'] ?? 0) !== $snapshotId) {
                throw new \RuntimeException('Snapshot latest no coincide.');
            }
            $steps[] = [
                'step' => 'latest_snapshot',
                'ok' => true,
                'message' => 'Snapshot latest validado.',
            ];

            $lines = $repo->get_lines_for_snapshot($snapshotId);
            $lineMap = [];
            foreach ($lines as $line) {
                $pid = isset($line['product_id']) ? (int) $line['product_id'] : 0;
                $qty = isset($line['qty_units']) ? (int) $line['qty_units'] : 0;
                if ($pid > 0) {
                    $lineMap[$pid] = $qty;
                }
            }
            if (($lineMap[$productId] ?? null) !== 1 || ($lineMap[$productId + 1] ?? null) !== 2) {
                throw new \RuntimeException('Validación de líneas fallida.');
            }
            $steps[] = [
                'step' => 'lines_validate',
                'ok' => true,
                'message' => 'Líneas validadas.',
            ];
        } catch (\Throwable $exception) {
            $steps[] = [
                'step' => 'error',
                'ok' => false,
                'message' => 'Selftest falló: ' . $exception->getMessage(),
            ];
        } finally {
            if ($eventId > 0) {
                $repo->delete_selftest_snapshots_for_event($eventId);
                $steps[] = [
                    'step' => 'cleanup_final',
                    'ok' => true,
                    'message' => 'Limpieza final completada.',
                ];
            }
        }

        return $steps;
    }
}
