<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class CostDefaultsPage
{
    private const META_UNIT_COST = '_bressol_default_unit_cogs_cents';
    private const META_UNIT_WEIGHT = '_bressol_unit_weight_grams';

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $products = $this->find_products($query);

        echo '<div class="wrap">';
        echo '<h1>Cost &amp; Margin - Cost Defaults</h1>';
        $this->render_notice();
        echo '<p class="description">Costes unitarios por defecto (sin IVA) y peso opcional por producto.</p>';

        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="bressol-cost-defaults" />';
        echo '<input type="text" name="q" value="' . esc_attr($query) . '" placeholder="Buscar por ID o título" style="min-width:240px;" />';
        echo '<button class="button">Buscar</button>';
        echo '</form>';

        if ($query !== '' && $products === []) {
            echo '<p>No se encontraron productos.</p>';
        }

        if ($products === []) {
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bressol_cost_defaults_save" />';
        echo '<input type="hidden" name="q" value="' . esc_attr($query) . '" />';
        wp_nonce_field('bressol_cost_defaults_save');

        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr>';
        echo '<th>Producto</th>';
        echo '<th>Unit COGS (cents, sin IVA)</th>';
        echo '<th>Peso unitario (grams)</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($products as $productId) {
            $title = get_the_title($productId);
            $label = $title !== '' ? $title : ('Producto #' . $productId);
            $link = admin_url('post.php?post=' . $productId . '&action=edit');
            $unitCogs = get_post_meta($productId, self::META_UNIT_COST, true);
            $unitWeight = get_post_meta($productId, self::META_UNIT_WEIGHT, true);
            $unitCogs = is_numeric($unitCogs) ? (int) $unitCogs : '';
            $unitWeight = is_numeric($unitWeight) ? (int) $unitWeight : '';

            echo '<tr>';
            echo '<td>';
            echo '<a href="' . esc_url($link) . '">' . esc_html($label) . '</a>';
            echo '<input type="hidden" name="product_ids[]" value="' . esc_attr((string) $productId) . '" />';
            echo '</td>';
            echo '<td>';
            echo '<input type="number" name="cost[' . esc_attr((string) $productId) . ']" min="1" step="1" value="' . esc_attr((string) $unitCogs) . '" />';
            echo '</td>';
            echo '<td>';
            echo '<input type="number" name="weight[' . esc_attr((string) $productId) . ']" min="0" step="1" value="' . esc_attr((string) $unitWeight) . '" />';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary">Guardar</button></p>';
        echo '</form>';
        echo '</div>';
    }

    /** @return array<int, int> */
    private function find_products(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        if (is_numeric($query)) {
            $productId = (int) $query;
            $post = get_post($productId);
            if ($post && $post->post_type === 'product') {
                return [$productId];
            }
            return [];
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

    private function render_notice(): void
    {
        $notice = isset($_GET['cost_defaults_notice']) ? sanitize_text_field(wp_unslash($_GET['cost_defaults_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $messages = [
            'updated' => ['success', 'Cost defaults guardados.'],
            'partial' => ['warning', 'Algunos registros tienen valores inválidos.'],
            'empty' => ['warning', 'No hay cambios para guardar.'],
            'forbidden' => ['error', 'No autorizado.'],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$class, $message] = $messages[$notice];
        if ($notice === 'partial' && isset($_GET['invalid'])) {
            $invalid = absint($_GET['invalid']);
            if ($invalid > 0) {
                $message .= ' Inválidos: ' . $invalid . '.';
            }
        }

        echo '<div class="notice notice-' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    }
}
