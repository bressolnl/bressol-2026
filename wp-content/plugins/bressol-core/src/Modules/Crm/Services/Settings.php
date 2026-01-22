<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    /** @return array<string, mixed> */
    public function get_all(): array
    {
        $defaults = [
            'b2c_points_per_euro' => 1.0,
            'b2b_points_per_euro' => 1.5,
            'expiry_months' => 12,
        ];

        $settings = get_option('bressol_crm_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        return array_merge($defaults, $settings);
    }

    public function get_float(string $key): float
    {
        $settings = $this->get_all();

        return isset($settings[$key]) ? (float) $settings[$key] : 0.0;
    }

    public function get_int(string $key): int
    {
        $settings = $this->get_all();

        return isset($settings[$key]) ? (int) $settings[$key] : 0;
    }

    /** @param array<string, mixed> $input */
    public function update(array $input): void
    {
        $current = $this->get_all();

        $current['b2c_points_per_euro'] = isset($input['b2c_points_per_euro'])
            ? max(0, (float) $input['b2c_points_per_euro'])
            : $current['b2c_points_per_euro'];

        $current['b2b_points_per_euro'] = isset($input['b2b_points_per_euro'])
            ? max(0, (float) $input['b2b_points_per_euro'])
            : $current['b2b_points_per_euro'];

        $current['expiry_months'] = isset($input['expiry_months'])
            ? max(1, (int) $input['expiry_months'])
            : $current['expiry_months'];

        update_option('bressol_crm_settings', $current);
    }
}
