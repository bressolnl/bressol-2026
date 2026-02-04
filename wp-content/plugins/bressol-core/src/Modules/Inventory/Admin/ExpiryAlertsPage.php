<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Admin;

use Bressol\Modules\Inventory\Services\ExpiryAlertsService;

if (!defined('ABSPATH')) {
    exit;
}

final class ExpiryAlertsPage
{
    private const META_CLEARANCE = '_bressol_clearance';

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        $service = new ExpiryAlertsService();
        $payload = $service->get_cached_or_build();
        $generatedAt = (string) ($payload['generated_at'] ?? '');
        $rows = isset($payload['lots']) && is_array($payload['lots']) ? $payload['lots'] : [];

        echo '<div class="wrap">';
        echo '<h1>Inventory - Expiry Alerts</h1>';
        $this->render_notice();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin: 12px 0;">';
        echo '<input type="hidden" name="action" value="bressol_refresh_expiry_alerts" />';
        wp_nonce_field('bressol_refresh_expiry_alerts');
        echo '<button type="submit" class="button">Refresh</button>';
        echo '</form>';
        if ($generatedAt !== '') {
            echo '<p class="description">Última actualización: ' . esc_html($generatedAt) . '</p>';
        }

        if ($rows === []) {
            echo '<p>No hay lotes en alerta.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:1200px;">';
        echo '<thead><tr>';
        echo '<th>Lote ID</th>';
        echo '<th>Producto</th>';
        echo '<th>Qty</th>';
        echo '<th>Caducidad</th>';
        echo '<th>Días</th>';
        echo '<th>Umbral</th>';
        echo '<th>Descuento recomendado</th>';
        echo '<th>Clearance</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lotId = (int) ($row['lot_id'] ?? 0);
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = (int) ($row['qty_on_hand'] ?? 0);
            $expiryDate = (string) ($row['expiry_date'] ?? '');
            $days = (int) ($row['days_to_expiry'] ?? 0);
            $bucket = (string) ($row['bucket'] ?? '');
            $discount = (int) ($row['recommended_discount_pct'] ?? 0);

            $title = $productId > 0 ? get_the_title($productId) : '';
            $label = $title !== '' ? $title : ('Producto #' . $productId);
            $link = $productId > 0 ? admin_url('post.php?post=' . $productId . '&action=edit') : '';
            $clearance = $productId > 0 ? get_post_meta($productId, self::META_CLEARANCE, true) : '';
            $isClearance = $clearance !== '';

            echo '<tr>';
            echo '<td>' . esc_html((string) $lotId) . '</td>';
            if ($link !== '') {
                echo '<td><a href="' . esc_url($link) . '">' . esc_html($label) . '</a></td>';
            } else {
                echo '<td>' . esc_html($label) . '</td>';
            }
            echo '<td>' . esc_html((string) $qty) . '</td>';
            echo '<td>' . esc_html($expiryDate) . '</td>';
            echo '<td>' . esc_html((string) $days) . '</td>';
            $bucketLabel = $bucket === 'expired' ? 'expired' : ('≤ ' . $bucket);
            echo '<td>' . esc_html($bucketLabel) . '</td>';
            echo '<td>' . esc_html((string) $discount) . '%</td>';
            echo '<td>';
            if ($isClearance) {
                echo '<span>Marcado</span>';
            } else {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="bressol_mark_clearance" />';
                echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $productId) . '" />';
                echo '<input type="hidden" name="lot_id" value="' . esc_attr((string) $lotId) . '" />';
                wp_nonce_field('bressol_mark_clearance');
                echo '<button type="submit" class="button">Marcar</button>';
                echo '</form>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function render_notice(): void
    {
        $notice = isset($_GET['expiry_notice']) ? sanitize_text_field(wp_unslash($_GET['expiry_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $messages = [
            'clearance_ok' => ['success', 'Producto marcado como clearance.'],
            'invalid' => ['error', 'Datos inválidos.'],
            'forbidden' => ['error', 'No autorizado.'],
            'refreshed' => ['success', 'Expiry alerts actualizadas.'],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$class, $message] = $messages[$notice];
        echo '<div class="notice notice-' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    }
}
