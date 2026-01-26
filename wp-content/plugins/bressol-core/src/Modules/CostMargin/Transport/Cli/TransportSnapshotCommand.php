<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Transport\Cli;

use Bressol\Modules\CostMargin\Transport\Repositories\TransportSnapshotRepository;
use Bressol\Modules\CostMargin\Transport\Services\TransportAllocator;

if (!defined('ABSPATH')) {
    exit;
}

final class TransportSnapshotCommand
{
    public function create(array $args, array $assocArgs): void
    {
        $transferId = isset($assocArgs['transfer_id']) ? (int) $assocArgs['transfer_id'] : 0;
        $totalCost = isset($assocArgs['total_cost_cents']) ? (int) $assocArgs['total_cost_cents'] : 0;
        if ($transferId <= 0) {
            \WP_CLI::error('Missing --transfer_id.');
        }

        $allocator = new TransportAllocator();
        $snapshotId = $allocator->create_snapshot($transferId, $totalCost, get_current_user_id(), null);
        $allocator->recalculate_allocations($snapshotId);

        \WP_CLI::success('Snapshot created: ' . $snapshotId);
    }

    public function close(array $args, array $assocArgs): void
    {
        $transferId = isset($assocArgs['transfer_id']) ? (int) $assocArgs['transfer_id'] : 0;
        if ($transferId <= 0) {
            \WP_CLI::error('Missing --transfer_id.');
        }

        $repo = new TransportSnapshotRepository();
        $snapshot = $repo->get_by_transfer_id($transferId);
        if (!$snapshot) {
            \WP_CLI::error('Snapshot not found.');
        }

        $allocator = new TransportAllocator();
        $allocator->close_snapshot((int) $snapshot['id'], get_current_user_id());
        \WP_CLI::success('Snapshot closed: ' . (int) $snapshot['id']);
    }

    public function show(array $args, array $assocArgs): void
    {
        $transferId = isset($assocArgs['transfer_id']) ? (int) $assocArgs['transfer_id'] : 0;
        if ($transferId <= 0) {
            \WP_CLI::error('Missing --transfer_id.');
        }

        $repo = new TransportSnapshotRepository();
        $snapshot = $repo->get_by_transfer_id($transferId);
        if (!$snapshot) {
            \WP_CLI::error('Snapshot not found.');
        }

        $allocator = new TransportAllocator();
        $allocations = $allocator->get_allocations((int) $snapshot['id']);

        \WP_CLI::log('Snapshot #' . (int) $snapshot['id'] . ' status=' . (string) $snapshot['status']);
        foreach ($allocations as $allocation) {
            \WP_CLI::log(
                'line=' . (int) $allocation['transfer_line_id']
                . ' lot=' . (int) $allocation['lot_id_nl']
                . ' qty=' . (int) $allocation['qty_units']
                . ' weight=' . (int) $allocation['weight_total_grams']
                . ' cost=' . (int) $allocation['allocated_cost_cents']
            );
        }
    }
}
