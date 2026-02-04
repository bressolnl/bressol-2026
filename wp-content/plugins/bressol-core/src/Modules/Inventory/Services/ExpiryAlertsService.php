<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Services;

use Bressol\Modules\CostMargin\Services\MarginRulesService;

if (!defined('ABSPATH')) {
    exit;
}

final class ExpiryAlertsService
{
    private const OPTION_KEY = 'bressol_inventory_expiry_alerts';

    /** @return array<string, mixed> */
    public function get_cached_or_build(): array
    {
        $today = current_time('Y-m-d');
        $cached = get_option(self::OPTION_KEY, []);
        if (is_array($cached) && isset($cached['generated_at'])) {
            $generatedAt = (string) $cached['generated_at'];
            if ($generatedAt !== '' && strpos($generatedAt, $today) === 0) {
                return $cached;
            }
        }

        return $this->refresh_cache();
    }

    /** @return array<string, mixed> */
    public function refresh_cache(): array
    {
        $payload = $this->build_alerts();
        update_option(self::OPTION_KEY, $payload, false);
        return $payload;
    }

    /** @return array<string, mixed> */
    private function build_alerts(): array
    {
        $today = current_time('Y-m-d');
        $limit = $this->add_days($today, 45);

        global $wpdb;
        $table = $wpdb->prefix . 'bressol_stock_lots';
        $sql = "SELECT id, product_id, qty_on_hand, expiry_date, unit_cogs_cents
            FROM {$table}
            WHERE location = %s AND qty_on_hand > 0 AND expiry_date IS NOT NULL AND expiry_date <= %s
            ORDER BY expiry_date ASC, id ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, 'NL', $limit), ARRAY_A);
        $rows = is_array($rows) ? $rows : [];

        $rules = new MarginRulesService();
        $hasDiscount = method_exists($rules, 'get_expiry_discount_pct');
        $alerts = [];
        foreach ($rows as $row) {
            $expiry = (string) ($row['expiry_date'] ?? '');
            $days = $this->days_to_expiry($expiry);
            if ($days === null || $days > 45) {
                continue;
            }

            $bucket = $this->resolve_bucket($days);
            if ($bucket === null) {
                continue;
            }

            $unitCogs = (int) ($row['unit_cogs_cents'] ?? 0);
            $riskValue = (int) ($row['qty_on_hand'] ?? 0) * $unitCogs;
            $discount = $hasDiscount ? (int) $rules->get_expiry_discount_pct($days) : 0;

            $alerts[] = [
                'lot_id' => (int) ($row['id'] ?? 0),
                'product_id' => (int) ($row['product_id'] ?? 0),
                'qty_on_hand' => (int) ($row['qty_on_hand'] ?? 0),
                'unit_cogs_cents' => $unitCogs,
                'expiry_date' => $expiry,
                'days_to_expiry' => $days,
                'bucket' => $bucket,
                'risk_value_cents' => $riskValue,
                'recommended_discount_pct' => $discount,
            ];
        }

        usort($alerts, static function (array $a, array $b): int {
            $daysA = (int) ($a['days_to_expiry'] ?? 0);
            $daysB = (int) ($b['days_to_expiry'] ?? 0);
            $expiredA = $daysA < 0;
            $expiredB = $daysB < 0;
            if ($expiredA !== $expiredB) {
                return $expiredA ? -1 : 1;
            }
            if ($daysA !== $daysB) {
                return $daysA <=> $daysB;
            }
            $riskA = (int) ($a['risk_value_cents'] ?? 0);
            $riskB = (int) ($b['risk_value_cents'] ?? 0);
            return $riskB <=> $riskA;
        });

        return [
            'generated_at' => current_time('mysql'),
            'lots' => $alerts,
        ];
    }

    private function resolve_bucket(int $days): ?string
    {
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 7) {
            return '7';
        }
        if ($days <= 21) {
            return '21';
        }
        if ($days <= 45) {
            return '45';
        }
        return null;
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

    private function days_to_expiry(string $expiry): ?int
    {
        $expiry = trim($expiry);
        if ($expiry === '') {
            return null;
        }
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $today = \DateTimeImmutable::createFromFormat('Y-m-d', current_time('Y-m-d'), $tz);
        $exp = \DateTimeImmutable::createFromFormat('Y-m-d', $expiry, $tz);
        if (!$today || !$exp) {
            return null;
        }
        return (int) $today->diff($exp)->format('%r%a');
    }
}
