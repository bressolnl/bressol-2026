<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    private const OPTION_NAME = 'bressol_crm_settings';

    /** @return array<string, float|int|string> */
    public function get_defaults(): array
    {
        return [
            'b2c_points_per_euro' => 1.0,
            'b2b_points_per_euro' => 1.0,
            'expiry_months' => 12,
            'retention_months' => 24,
            'retention_basis' => 'last_order',
        ];
    }

    /** @return array<string, float|int|string> */
    public function get_settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $merged = array_merge($this->get_defaults(), $settings);

        $merged['b2c_points_per_euro'] = (float) ($merged['b2c_points_per_euro'] ?? 1.0);
        $merged['b2b_points_per_euro'] = (float) ($merged['b2b_points_per_euro'] ?? 1.0);
        $merged['expiry_months'] = max(1, (int) ($merged['expiry_months'] ?? 12));
        $merged['retention_months'] = max(1, (int) ($merged['retention_months'] ?? 24));

        $basis = (string) ($merged['retention_basis'] ?? 'last_order');
        $merged['retention_basis'] = in_array($basis, ['last_order', 'created_at'], true) ? $basis : 'last_order';

        return $merged;
    }

    public function ensure_defaults(): void
    {
        $current = get_option(self::OPTION_NAME, null);
        if ($current === null) {
            update_option(self::OPTION_NAME, $this->get_defaults(), false);
        }
    }
}
