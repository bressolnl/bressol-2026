<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerSearchService
{
    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function search(string $q, int $page, int $perPage): array
    {
        global $wpdb;

        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $table = $wpdb->prefix . 'bressol_crm_customers';
        // Hardening: si faltan tablas, no lanzar warnings SQL.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
        $q = trim($q);

        $whereClause = '1=1';
        $params = [];
        $emailLikeFallback = false;

        if ($q !== '') {
            if (ctype_digit($q)) {
                $whereClause = 'id = %d';
                $params[] = (int) $q;
            } elseif (strpos($q, '@') !== false) {
                $whereClause = 'email = %s';
                $params[] = $q;
                $emailLikeFallback = true;
            } else {
                $tokens = preg_split('/\s+/', $q) ?: [];
                $tokenClauses = [];
                foreach ($tokens as $token) {
                    $token = trim($token);
                    if ($token === '') {
                        continue;
                    }
                    $tokenClauses[] = '(first_name LIKE %s OR last_name LIKE %s)';
                    $like = '%' . $wpdb->esc_like($token) . '%';
                    $params[] = $like;
                    $params[] = $like;
                }
                if ($tokenClauses) {
                    $whereClause = implode(' AND ', $tokenClauses);
                } else {
                    // Si no hay tokens útiles, no devolvemos todo el dataset.
                    $whereClause = '0=1';
                    $params = [];
                }
            }
        }

        $whereSql = $params ? $wpdb->prepare($whereClause, $params) : $whereClause;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$whereSql}");
        if ($emailLikeFallback && $total === 0) {
            $whereClause = 'email LIKE %s';
            $params = ['%' . $wpdb->esc_like($q) . '%'];
            $whereSql = $wpdb->prepare($whereClause, $params);
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$whereSql}");
        }

        $offset = ($page - 1) * $perPage;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, email, first_name, last_name, customer_type, status, loyalty_enabled, created_at, updated_at
                 FROM {$table}
                 WHERE {$whereSql}
                 ORDER BY id DESC
                 LIMIT %d OFFSET %d",
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        $items = [];
        foreach ((array) $rows as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'email' => (string) ($row['email'] ?? ''),
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'customer_type' => (string) ($row['customer_type'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'loyalty_enabled' => (int) ($row['loyalty_enabled'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}