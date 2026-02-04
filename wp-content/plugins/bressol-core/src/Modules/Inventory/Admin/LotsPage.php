<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Admin;

use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class LotsPage
{
    private const META_UNIT_COST = '_bressol_default_unit_cogs_cents';
    private const META_UNIT_WEIGHT = '_bressol_unit_weight_grams';

    private LotRepository $lotRepository;

    public function __construct(?LotRepository $lotRepository = null)
    {
        $this->lotRepository = $lotRepository ?? new LotRepository();
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        $location = isset($_GET['location']) ? sanitize_text_field(wp_unslash($_GET['location'])) : 'all';
        $expiry = isset($_GET['expiry']) ? sanitize_text_field(wp_unslash($_GET['expiry'])) : '';
        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $onlyQty = !empty($_GET['only_qty']);
        $prefillProductId = isset($_GET['prefill_product_id']) ? absint($_GET['prefill_product_id']) : 0;
        if ($prefillProductId <= 0 && is_numeric($query)) {
            $prefillProductId = (int) $query;
        }

        $productsFilter = $this->resolve_product_filter($query);
        $lots = $this->query_lots($location, $expiry, $productsFilter, $onlyQty);

        $prefillCost = '';
        $prefillWeight = '';
        if ($prefillProductId > 0) {
            $metaCost = get_post_meta($prefillProductId, self::META_UNIT_COST, true);
            $metaWeight = get_post_meta($prefillProductId, self::META_UNIT_WEIGHT, true);
            $prefillCost = is_numeric($metaCost) ? (string) (int) $metaCost : '';
            $prefillWeight = is_numeric($metaWeight) ? (string) (int) $metaWeight : '';
        }

        echo '<div class="wrap">';
        echo '<h1>Inventory - Lots</h1>';
        $this->render_notice();

        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="bressol-lots" />';
        echo '<label>Ubicación ';
        echo '<select name="location">';
        echo '<option value="all"' . selected($location, 'all', false) . '>Todas</option>';
        echo '<option value="ES"' . selected($location, 'ES', false) . '>ES</option>';
        echo '<option value="NL"' . selected($location, 'NL', false) . '>NL</option>';
        echo '</select></label> ';
        echo '<label>Caducidad ';
        echo '<select name="expiry">';
        echo '<option value=""' . selected($expiry, '', false) . '>Todas</option>';
        echo '<option value="expired"' . selected($expiry, 'expired', false) . '>Caducados</option>';
        echo '<option value="45"' . selected($expiry, '45', false) . '>&le; 45 días</option>';
        echo '<option value="21"' . selected($expiry, '21', false) . '>&le; 21 días</option>';
        echo '<option value="7"' . selected($expiry, '7', false) . '>&le; 7 días</option>';
        echo '</select></label> ';
        echo '<label>Producto ';
        echo '<input type="text" name="q" value="' . esc_attr($query) . '" placeholder="ID o título" />';
        echo '</label> ';
        echo '<label><input type="checkbox" name="only_qty" value="1"' . checked($onlyQty, true, false) . ' /> Solo qty&gt;0</label> ';
        echo '<button class="button">Filtrar</button>';
        echo '</form>';

        echo '<h2>Agregar lote ES</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bressol_lot_create_es" />';
        wp_nonce_field('bressol_lot_create_es');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">Producto ID</th><td><input type="number" name="product_id" min="1" step="1" value="' . esc_attr((string) $prefillProductId) . '" /></td></tr>';
        echo '<tr><th scope="row">Cantidad</th><td><input type="number" name="qty" min="1" step="1" /></td></tr>';
        echo '<tr><th scope="row">Caducidad</th><td><input type="date" name="expiry_date" /></td></tr>';
        echo '<tr><th scope="row">Unit COGS (cents)</th><td><input type="number" name="unit_cogs_cents" min="1" step="1" value="' . esc_attr($prefillCost) . '" /></td></tr>';
        echo '<tr><th scope="row">Peso unitario (grams)</th><td><input type="number" name="unit_weight_grams" min="0" step="1" value="' . esc_attr($prefillWeight) . '" /></td></tr>';
        echo '<tr><th scope="row">Nota</th><td><input type="text" name="note" maxlength="200" style="min-width:320px;" /></td></tr>';
        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary">Crear lote ES</button></p>';
        echo '</form>';

        echo '<h2>Ajustar qty de lote</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bressol_lot_adjust" />';
        wp_nonce_field('bressol_lot_adjust');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">Lote ID</th><td><input type="number" name="lot_id" min="1" step="1" value="' . esc_attr(isset($_GET['prefill_lot_id']) ? (string) absint($_GET['prefill_lot_id']) : '') . '" /></td></tr>';
        echo '<tr><th scope="row">Delta (+/-)</th><td><input type="number" name="delta" step="1" /></td></tr>';
        echo '<tr><th scope="row">Razón</th><td>';
        echo '<select name="reason">';
        echo '<option value="count">Reconteo</option>';
        echo '<option value="damage">Daño</option>';
        echo '<option value="correction">Corrección</option>';
        echo '<option value="other">Otro</option>';
        echo '</select>';
        echo '</td></tr>';
        echo '<tr><th scope="row">Nota</th><td><input type="text" name="note" maxlength="200" style="min-width:320px;" /></td></tr>';
        echo '</tbody></table>';
        echo '<p><button type="submit" class="button">Ajustar</button></p>';
        echo '</form>';

        echo '<h2>Listado de lotes</h2>';
        if ($lots === []) {
            echo '<p>No hay lotes para mostrar.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:1200px;">';
        echo '<thead><tr>';
        echo '<th>Lote ID</th>';
        echo '<th>Producto</th>';
        echo '<th>Ubicación</th>';
        echo '<th>Qty</th>';
        echo '<th>Unit COGS</th>';
        echo '<th>Caducidad</th>';
        echo '<th>Días</th>';
        echo '<th>Creado</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($lots as $lot) {
            $lotId = (int) ($lot['id'] ?? 0);
            $productId = (int) ($lot['product_id'] ?? 0);
            $locationLabel = (string) ($lot['location'] ?? '');
            $qty = (int) ($lot['qty_on_hand'] ?? 0);
            $unitCogs = (int) ($lot['unit_cogs_cents'] ?? 0);
            $expiryDate = (string) ($lot['expiry_date'] ?? '');
            $createdAt = (string) ($lot['created_at'] ?? '');

            $title = $productId > 0 ? get_the_title($productId) : '';
            $label = $title !== '' ? $title : ('Producto #' . $productId);
            $link = $productId > 0 ? admin_url('post.php?post=' . $productId . '&action=edit') : '';
            $daysToExpiry = $this->format_days_to_expiry($expiryDate);

            echo '<tr>';
            echo '<td>' . esc_html((string) $lotId) . '</td>';
            if ($link !== '') {
                echo '<td><a href="' . esc_url($link) . '">' . esc_html($label) . '</a></td>';
            } else {
                echo '<td>' . esc_html($label) . '</td>';
            }
            echo '<td>' . esc_html($locationLabel) . '</td>';
            echo '<td>' . esc_html((string) $qty) . '</td>';
            echo '<td>' . esc_html((string) $unitCogs) . '</td>';
            echo '<td>' . esc_html($expiryDate !== '' ? $expiryDate : '-') . '</td>';
            echo '<td>' . esc_html($daysToExpiry) . '</td>';
            echo '<td>' . esc_html($createdAt) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /** @return array<int, int> */
    private function resolve_product_filter(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        if (is_numeric($query)) {
            return [(int) $query];
        }

        $search = new \WP_Query([
            'post_type' => 'product',
            's' => $query,
            'posts_per_page' => 50,
            'fields' => 'ids',
        ]);

        if (!is_array($search->posts)) {
            return [];
        }

        return array_map('intval', $search->posts);
    }

    /** @return array<int, array<string, mixed>> */
    private function query_lots(string $location, string $expiry, array $productIds, bool $onlyQty): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_stock_lots';

        $where = [];
        $params = [];

        $location = strtoupper($location);
        if (in_array($location, ['ES', 'NL'], true)) {
            $where[] = 'location = %s';
            $params[] = $location;
        }

        if ($onlyQty) {
            $where[] = 'qty_on_hand > 0';
        }

        if ($productIds !== []) {
            $placeholders = implode(',', array_fill(0, count($productIds), '%d'));
            $where[] = "product_id IN ({$placeholders})";
            $params = array_merge($params, $productIds);
        }

        $today = current_time('Y-m-d');
        if ($expiry === 'expired') {
            $where[] = 'expiry_date IS NOT NULL AND expiry_date < %s';
            $params[] = $today;
        } elseif (in_array($expiry, ['45', '21', '7'], true)) {
            $days = (int) $expiry;
            $limit = $this->add_days($today, $days);
            $where[] = 'expiry_date IS NOT NULL AND expiry_date <= %s';
            $params[] = $limit;
        }

        $sql = "SELECT * FROM {$table}";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 200';

        $prepared = $params !== [] ? $wpdb->prepare($sql, $params) : $sql;
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    private function add_days(string $date, int $days): string
    {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);
        if (!$dt) {
            return $date;
        }
        return $dt->modify('+' . $days . ' days')->format('Y-m-d');
    }

    private function format_days_to_expiry(string $expiry): string
    {
        $expiry = trim($expiry);
        if ($expiry === '') {
            return '-';
        }
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $today = \DateTimeImmutable::createFromFormat('Y-m-d', current_time('Y-m-d'), $tz);
        $exp = \DateTimeImmutable::createFromFormat('Y-m-d', $expiry, $tz);
        if (!$today || !$exp) {
            return '-';
        }
        return (string) $today->diff($exp)->format('%r%a');
    }

    private function render_notice(): void
    {
        $notice = isset($_GET['lots_notice']) ? sanitize_text_field(wp_unslash($_GET['lots_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $messages = [
            'created' => ['success', 'Lote ES creado.'],
            'adjusted' => ['success', 'Ajuste aplicado.'],
            'create_failed' => ['error', 'No se pudo crear el lote.'],
            'create_move_failed' => ['warning', 'Lote creado, pero no se pudo registrar el movimiento.'],
            'adjust_failed' => ['error', 'No se pudo ajustar el lote.'],
            'adjust_move_failed' => ['warning', 'Ajuste aplicado, pero no se pudo registrar el movimiento.'],
            'default_cost_missing' => ['error', 'Falta el coste default del producto.'],
            'invalid_input' => ['error', 'Datos inválidos para crear lote.'],
            'invalid_product' => ['error', 'Producto inválido.'],
            'invalid_expiry' => ['error', 'Fecha de caducidad inválida.'],
            'invalid_adjust' => ['error', 'Datos inválidos para ajustar.'],
            'invalid_lot' => ['error', 'Lote no encontrado.'],
            'forbidden' => ['error', 'No autorizado.'],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$class, $message] = $messages[$notice];
        if ($notice === 'default_cost_missing') {
            $url = admin_url('admin.php?page=bressol-cost-defaults');
            $message .= ' <a href="' . esc_url($url) . '">Ir a Cost Defaults</a>.';
        }

        echo '<div class="notice notice-' . esc_attr($class) . '"><p>' . wp_kses_post($message) . '</p></div>';
    }
}
