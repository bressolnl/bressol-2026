<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Admin;

use Bressol\Modules\CostMargin\Transport\Repositories\TransportSnapshotRepository;
use Bressol\Modules\CostMargin\Transport\Services\TransportAllocator;

if (!defined('ABSPATH')) {
    exit;
}

final class TransportPage
{
    private TransportSnapshotRepository $snapshotRepository;
    private TransportAllocator $allocator;

    public function __construct(
        ?TransportSnapshotRepository $snapshotRepository = null,
        ?TransportAllocator $allocator = null
    ) {
        $this->snapshotRepository = $snapshotRepository ?? new TransportSnapshotRepository();
        $this->allocator = $allocator ?? new TransportAllocator();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        $snapshotId = isset($_GET['snapshot_id']) ? absint($_GET['snapshot_id']) : 0;
        $allocations = $snapshotId > 0 ? $this->allocator->get_allocations($snapshotId) : [];
        $receivedTransfers = $this->query_received_transfers();

        echo '<div class="wrap">';
        echo '<h1>Cost &amp; Margin - Transport</h1>';
        $this->render_notice();

        echo '<h2>Crear snapshot</h2>';
        if ($receivedTransfers === []) {
            echo '<p>No hay transfers received disponibles.</p>';
        } else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="bressol_transport_snapshot_create" />';
            wp_nonce_field('bressol_transport_snapshot_create');
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Transfer (received)</th><td>';
            echo '<select name="transfer_id">';
            foreach ($receivedTransfers as $row) {
                $id = (int) ($row['id'] ?? 0);
                $label = '#' . $id . ' - ' . (string) ($row['received_at'] ?? '');
                echo '<option value="' . esc_attr((string) $id) . '">' . esc_html($label) . '</option>';
            }
            echo '</select></td></tr>';
            echo '<tr><th scope="row">Total cost (cents)</th><td><input type="number" name="total_cost_cents" min="0" step="1" /></td></tr>';
            echo '<tr><th scope="row">Nota</th><td><input type="text" name="note" maxlength="200" style="min-width:320px;" /></td></tr>';
            echo '</tbody></table>';
            echo '<p><button type="submit" class="button button-primary">Crear snapshot</button></p>';
            echo '</form>';
        }

        echo '<h2>Snapshots</h2>';
        $snapshots = $this->query_snapshots();
        if ($snapshots === []) {
            echo '<p>No hay snapshots.</p>';
        } else {
            echo '<table class="widefat striped" style="max-width:1100px;">';
            echo '<thead><tr>';
            echo '<th>ID</th>';
            echo '<th>Transfer</th>';
            echo '<th>Status</th>';
            echo '<th>Total cost</th>';
            echo '<th>Created</th>';
            echo '<th>Closed</th>';
            echo '<th>Acciones</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($snapshots as $snapshot) {
                $id = (int) ($snapshot['id'] ?? 0);
                $status = (string) ($snapshot['status'] ?? '');
                $viewLink = admin_url('admin.php?page=bressol-transport&snapshot_id=' . $id);
                echo '<tr>';
                echo '<td>' . esc_html((string) $id) . '</td>';
                echo '<td>' . esc_html((string) ($snapshot['transfer_id'] ?? '')) . '</td>';
                echo '<td>' . esc_html($status) . '</td>';
                echo '<td>' . esc_html((string) ($snapshot['total_cost_cents'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($snapshot['created_at'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($snapshot['closed_at'] ?? '')) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url($viewLink) . '">Ver</a> ';
                if ($status === 'draft') {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
                    echo '<input type="hidden" name="action" value="bressol_transport_snapshot_recalc" />';
                    echo '<input type="hidden" name="snapshot_id" value="' . esc_attr((string) $id) . '" />';
                    wp_nonce_field('bressol_transport_snapshot_recalc');
                    echo '<button type="submit" class="button">Recalc</button>';
                    echo '</form> ';
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
                    echo '<input type="hidden" name="action" value="bressol_transport_snapshot_close" />';
                    echo '<input type="hidden" name="snapshot_id" value="' . esc_attr((string) $id) . '" />';
                    wp_nonce_field('bressol_transport_snapshot_close');
                    echo '<button type="submit" class="button">Close</button>';
                    echo '</form>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        if ($snapshotId > 0) {
            echo '<h2>Allocations</h2>';
            if ($allocations === []) {
                echo '<p>No hay allocations.</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:1100px;">';
                echo '<thead><tr>';
                echo '<th>Transfer line</th>';
                echo '<th>Lot NL</th>';
                echo '<th>Qty</th>';
                echo '<th>Weight total</th>';
                echo '<th>Allocated cost</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                foreach ($allocations as $allocation) {
                    echo '<tr>';
                    echo '<td>' . esc_html((string) ($allocation['transfer_line_id'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($allocation['lot_id_nl'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($allocation['qty_units'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($allocation['weight_total_grams'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($allocation['allocated_cost_cents'] ?? '')) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        }

        echo '</div>';
    }

    /** @return array<int, array<string, mixed>> */
    private function query_snapshots(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_transport_snapshots';
        $sql = "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT 200";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int, array<string, mixed>> */
    private function query_received_transfers(): array
    {
        global $wpdb;
        $transfers = $wpdb->prefix . 'bressol_stock_transfers';
        $snapshots = $wpdb->prefix . 'bressol_transport_snapshots';
        $sql = "SELECT t.id, t.received_at
            FROM {$transfers} t
            LEFT JOIN {$snapshots} s ON s.transfer_id = t.id
            WHERE t.status = %s AND s.id IS NULL
            ORDER BY t.received_at DESC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, 'received'), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    private function render_notice(): void
    {
        $notice = isset($_GET['transport_notice']) ? sanitize_text_field(wp_unslash($_GET['transport_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $messages = [
            'created' => ['success', 'Snapshot creado.'],
            'recalc_ok' => ['success', 'Allocations recalculadas.'],
            'close_ok' => ['success', 'Snapshot cerrado.'],
            'invalid' => ['error', 'Datos inválidos.'],
            'invalid_transfer' => ['error', 'Transfer inválido o no recibido.'],
            'failed' => ['error', 'No se pudo completar la acción.'],
            'forbidden' => ['error', 'No autorizado.'],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$class, $message] = $messages[$notice];
        echo '<div class="notice notice-' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    }
}
