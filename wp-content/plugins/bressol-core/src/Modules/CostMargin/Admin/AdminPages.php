<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Admin;

use Bressol\Modules\CostMargin\Services\MarginRulesService;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    public function registerMenus(): void
    {
        add_submenu_page(
            'bressol',
            'Diagnostics',
            'Diagnostics',
            'manage_options',
            'bressol-diagnostics',
            [$this, 'renderDiagnosticsPage']
        );
    }

    public function renderDiagnosticsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        $channel = isset($_GET['channel']) ? sanitize_text_field(wp_unslash($_GET['channel'])) : 'pos';
        if (!in_array($channel, ['pos', 'online'], true)) {
            $channel = 'pos';
        }

        $results = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bressol_margin_selftest_submit'])) {
            check_admin_referer('bressol_margin_selftest');
            $results = $this->run_selftest($channel);
        }

        echo '<div class="wrap">';
        echo '<h1>Bressol - Diagnostics</h1>';
        echo '<p class="description">Selftest del motor de márgenes (sin WP-CLI).</p>';

        echo '<form method="post" style="margin:12px 0;">';
        wp_nonce_field('bressol_margin_selftest');
        echo '<label>Canal ';
        echo '<select name="channel">';
        echo '<option value="pos" ' . selected($channel, 'pos', false) . '>POS</option>';
        echo '<option value="online" ' . selected($channel, 'online', false) . '>Online</option>';
        echo '</select></label> ';
        echo '<button type="submit" name="bressol_margin_selftest_submit" class="button button-primary">Run Margin Selftest</button>';
        echo '</form>';

        if (is_array($results)) {
            $summary = $results['summary'];
            $statusClass = $summary['fail'] > 0 ? 'notice-error' : 'notice-success';
            echo '<p class="notice ' . esc_attr($statusClass) . '" style="padding:8px 12px;">';
            echo 'Resultados: PASS ' . esc_html((string) $summary['pass']) . ' / FAIL ' . esc_html((string) $summary['fail']);
            echo '</p>';

            echo '<table class="widefat striped" style="max-width:960px;">';
            echo '<thead><tr><th>Case</th><th>Expected</th><th>Got</th><th>Result</th><th>Details</th></tr></thead>';
            echo '<tbody>';
            foreach ($results['rows'] as $row) {
                $ok = !empty($row['ok']);
                echo '<tr>';
                echo '<td>' . esc_html((string) $row['name']) . '</td>';
                echo '<td>' . esc_html((string) $row['expected']) . '</td>';
                echo '<td>' . esc_html((string) $row['got']) . '</td>';
                echo '<td>' . esc_html($ok ? 'PASS' : 'FAIL') . '</td>';
                echo '<td>' . esc_html((string) $row['details']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /** @return array{summary:array{pass:int,fail:int},rows:array<int, array<string, mixed>>} */
    private function run_selftest(string $channel): array
    {
        $service = new MarginRulesService();
        $cases = $service->get_selftest_cases($channel);
        $rows = [];
        $pass = 0;
        $fail = 0;

        foreach ($cases as $case) {
            $result = $service->evaluate_cart($case['items'], $case['context']);
            $status = (string) ($result['status'] ?? 'pass');
            $expected = (string) ($case['expect'] ?? 'pass');
            $ok = $status === $expected;

            $computed = isset($result['computed']) && is_array($result['computed']) ? $result['computed'] : [];
            $profit = isset($computed['profit_cents']) ? (int) $computed['profit_cents'] : 0;
            $marginPct = isset($computed['margin_pct']) ? (float) $computed['margin_pct'] : 0.0;
            $missing = !empty($computed['missing_cost']) ? 'yes' : 'no';
            $violations = isset($result['violations']) && is_array($result['violations']) ? $result['violations'] : [];
            $codes = [];
            foreach ($violations as $violation) {
                if (is_array($violation) && isset($violation['code'])) {
                    $codes[] = (string) $violation['code'];
                }
            }

            $details = 'profit=' . $profit . ' margin=' . round($marginPct * 100, 2) . '% missing_cost=' . $missing;
            if ($codes !== []) {
                $details .= ' violations=' . implode(',', $codes);
            }

            $rows[] = [
                'name' => (string) ($case['name'] ?? ''),
                'expected' => $expected,
                'got' => $status,
                'ok' => $ok,
                'details' => $details,
            ];

            if ($ok) {
                $pass++;
            } else {
                $fail++;
            }
        }

        return [
            'summary' => [
                'pass' => $pass,
                'fail' => $fail,
            ],
            'rows' => $rows,
        ];
    }
}
