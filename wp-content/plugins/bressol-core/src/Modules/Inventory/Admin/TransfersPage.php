<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Admin;

use Bressol\Modules\Inventory\Transfers\Repositories\TransferLineRepository;
use Bressol\Modules\Inventory\Transfers\Repositories\TransferRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class TransfersPage
{
    private TransferRepository $transferRepository;
    private TransferLineRepository $lineRepository;

    public function __construct(
        ?TransferRepository $transferRepository = null,
        ?TransferLineRepository $lineRepository = null
    ) {
        $this->transferRepository = $transferRepository ?? new TransferRepository();
        $this->lineRepository = $lineRepository ?? new TransferLineRepository();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        $transferId = isset($_GET['transfer_id']) ? absint($_GET['transfer_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $transfers = $this->query_transfers($status);
        $transfer = $transferId > 0 ? $this->transferRepository->get_transfer($transferId) : null;
        $lines = $transferId > 0 ? $this->lineRepository->get_lines($transferId) : [];

        echo '<div class="wrap">';
        echo '<h1>Inventory - Transfers</h1>';
        $this->render_notice();

        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="bressol-transfers" />';
        echo '<label>Estado ';
        echo '<select name="status">';
        echo '<option value=""' . selected($status, '', false) . '>Todos</option>';
        echo '<option value="draft"' . selected($status, 'draft', false) . '>Draft</option>';
        echo '<option value="shipped"' . selected($status, 'shipped', false) . '>Shipped</option>';
        echo '<option value="received"' . selected($status, 'received', false) . '>Received</option>';
        echo '</select></label> ';
        echo '<button class="button">Filtrar</button>';
        echo '</form>';

        echo '<h2>Crear transfer</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bressol_transfer_create" />';
        wp_nonce_field('bressol_transfer_create');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">Nota</th><td><input type="text" name="note" maxlength="200" style="min-width:320px;" /></td></tr>';
        echo '</tbody></table>';
        $this->render_lines_editor();
        echo '<p><button type="submit" class="button button-primary">Crear transfer</button></p>';
        echo '</form>';

        if ($transfer) {
            $statusLabel = (string) ($transfer['status'] ?? '');
            echo '<h2>Transfer #' . esc_html((string) $transferId) . ' (' . esc_html($statusLabel) . ')</h2>';
            if ($lines === []) {
                echo '<p>Sin líneas.</p>';
            } else {
                $this->render_lines_table($lines);
            }

            if ($statusLabel === 'draft') {
                echo '<h3>Editar líneas</h3>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="bressol_transfer_save_lines" />';
                echo '<input type="hidden" name="transfer_id" value="' . esc_attr((string) $transferId) . '" />';
                wp_nonce_field('bressol_transfer_save_lines');
                $this->render_lines_editor($lines);
                echo '<p><button type="submit" class="button button-primary">Guardar líneas</button></p>';
                echo '</form>';

                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="bressol_transfer_ship" />';
                echo '<input type="hidden" name="transfer_id" value="' . esc_attr((string) $transferId) . '" />';
                wp_nonce_field('bressol_transfer_ship');
                echo '<p><button type="submit" class="button">Ship</button></p>';
                echo '</form>';
            } elseif ($statusLabel === 'shipped') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="bressol_transfer_receive" />';
                echo '<input type="hidden" name="transfer_id" value="' . esc_attr((string) $transferId) . '" />';
                wp_nonce_field('bressol_transfer_receive');
                echo '<p><button type="submit" class="button button-primary">Receive</button></p>';
                echo '</form>';
            }
        }

        echo '<h2>Listado</h2>';
        if ($transfers === []) {
            echo '<p>No hay transfers.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Status</th>';
        echo '<th>Created</th>';
        echo '<th>Shipped</th>';
        echo '<th>Received</th>';
        echo '<th>Ver</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($transfers as $row) {
            $id = (int) ($row['id'] ?? 0);
            $link = admin_url('admin.php?page=bressol-transfers&transfer_id=' . $id);
            echo '<tr>';
            echo '<td>' . esc_html((string) $id) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['shipped_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['received_at'] ?? '')) . '</td>';
            echo '<td><a href="' . esc_url($link) . '">Abrir</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    /** @return array<int, array<string, mixed>> */
    private function query_transfers(string $status): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_stock_transfers';
        $params = [];
        $sql = "SELECT * FROM {$table}";
        if (in_array($status, ['draft', 'shipped', 'received'], true)) {
            $sql .= ' WHERE status = %s';
            $params[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 200';
        $prepared = $params !== [] ? $wpdb->prepare($sql, $params) : $sql;
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function render_lines_table(array $lines): void
    {
        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr>';
        echo '<th>Producto</th>';
        echo '<th>Qty</th>';
        echo '<th>Caducidad</th>';
        echo '<th>Unit COGS</th>';
        echo '<th>Peso unitario</th>';
        echo '<th>Peso total</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $title = $productId > 0 ? get_the_title($productId) : '';
            $label = $title !== '' ? $title : ('Producto #' . $productId);
            $link = $productId > 0 ? admin_url('post.php?post=' . $productId . '&action=edit') : '';
            echo '<tr>';
            if ($link !== '') {
                echo '<td><a href="' . esc_url($link) . '">' . esc_html($label) . '</a></td>';
            } else {
                echo '<td>' . esc_html($label) . '</td>';
            }
            echo '<td>' . esc_html((string) ($line['qty_units'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($line['expiry_date'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($line['unit_cogs_cents'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($line['unit_weight_override_grams'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($line['line_weight_total_grams'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array<string, mixed>> $prefill */
    private function render_lines_editor(array $prefill = []): void
    {
        $rows = $prefill;
        $extra = max(3, 6 - count($rows));
        if ($extra > 0) {
            $rows = array_merge($rows, array_fill(0, $extra, []));
        }
        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr>';
        echo '<th>Producto ID</th>';
        echo '<th>Qty</th>';
        echo '<th>Caducidad</th>';
        echo '<th>Unit COGS</th>';
        echo '<th>Peso unitario</th>';
        echo '<th>Peso total</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($rows as $line) {
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $qty = isset($line['qty_units']) ? (int) $line['qty_units'] : '';
            $expiry = isset($line['expiry_date']) ? (string) $line['expiry_date'] : '';
            $unitCogs = isset($line['unit_cogs_cents']) ? (int) $line['unit_cogs_cents'] : '';
            $unitWeight = isset($line['unit_weight_override_grams']) ? (int) $line['unit_weight_override_grams'] : '';
            $lineWeight = isset($line['line_weight_total_grams']) ? (int) $line['line_weight_total_grams'] : '';
            echo '<tr>';
            echo '<td><input type="number" name="line_product_id[]" min="1" step="1" value="' . esc_attr((string) $productId) . '" /></td>';
            echo '<td><input type="number" name="line_qty[]" min="1" step="1" value="' . esc_attr((string) $qty) . '" /></td>';
            echo '<td><input type="date" name="line_expiry[]" value="' . esc_attr($expiry) . '" /></td>';
            echo '<td><input type="number" name="line_unit_cogs[]" min="0" step="1" value="' . esc_attr((string) $unitCogs) . '" /></td>';
            echo '<td><input type="number" name="line_unit_weight[]" min="0" step="1" value="' . esc_attr((string) $unitWeight) . '" /></td>';
            echo '<td><input type="number" name="line_weight_total[]" min="0" step="1" value="' . esc_attr((string) $lineWeight) . '" /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private function render_notice(): void
    {
        $notice = isset($_GET['transfers_notice']) ? sanitize_text_field(wp_unslash($_GET['transfers_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $messages = [
            'created' => ['success', 'Transfer creado.'],
            'lines_saved' => ['success', 'Líneas actualizadas.'],
            'ship_ok' => ['success', 'Transfer enviado.'],
            'receive_ok' => ['success', 'Transfer recibido.'],
            'invalid' => ['error', 'Datos inválidos.'],
            'invalid_lines' => ['error', 'Líneas inválidas o vacías.'],
            'not_draft' => ['error', 'Solo se permite editar drafts.'],
            'ship_failed' => ['error', 'No se pudo enviar el transfer.'],
            'receive_failed' => ['error', 'No se pudo recibir el transfer.'],
            'forbidden' => ['error', 'No autorizado.'],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$class, $message] = $messages[$notice];
        echo '<div class="notice notice-' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    }
}
