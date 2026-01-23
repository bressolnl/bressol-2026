<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    private const OPTION_NAME = 'bressol_crm_settings';

    /** @return array<string, float|int> */
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

    /** @return array<string, float|int> */
    public function get_settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $defaults = $this->get_defaults();
        $merged = array_merge($defaults, $settings);

        $merged['b2c_points_per_euro'] = (float) $merged['b2c_points_per_euro'];
        $merged['b2b_points_per_euro'] = (float) $merged['b2b_points_per_euro'];
        $merged['expiry_months'] = max(1, (int) $merged['expiry_months']);
        $merged['retention_months'] = max(1, (int) $merged['retention_months']);
        $merged['retention_basis'] = $this->sanitize_retention_basis((string) $merged['retention_basis']);

        return $merged;
    }

    /** @param array<string, mixed> $input */
    public function update_settings(array $input): void
    {
        $settings = $this->get_settings();

        if (isset($input['b2c_points_per_euro'])) {
            $settings['b2c_points_per_euro'] = (float) $input['b2c_points_per_euro'];
        }

        if (isset($input['b2b_points_per_euro'])) {
            $settings['b2b_points_per_euro'] = (float) $input['b2b_points_per_euro'];
        }

        if (isset($input['expiry_months'])) {
            $settings['expiry_months'] = max(1, (int) $input['expiry_months']);
        }

        if (isset($input['retention_months'])) {
            $settings['retention_months'] = max(1, (int) $input['retention_months']);
        }

        if (isset($input['retention_basis'])) {
            $settings['retention_basis'] = $this->sanitize_retention_basis((string) $input['retention_basis']);
        }

        update_option(self::OPTION_NAME, $settings);
    }

    public function ensure_defaults(): void
    {
        if (get_option(self::OPTION_NAME, null) === null) {
            add_option(self::OPTION_NAME, $this->get_defaults(), '', 'no');
            return;
        }

        $settings = $this->get_settings();
        update_option(self::OPTION_NAME, $settings);
    }

    public function get_points_rate(string $customerType): float
    {
        $settings = $this->get_settings();

        if ($customerType === 'b2b') {
            return (float) $settings['b2b_points_per_euro'];
        }

        return (float) $settings['b2c_points_per_euro'];
    }

    public function get_expiry_months(): int
    {
        $settings = $this->get_settings();

        return (int) $settings['expiry_months'];
    }

    public function get_retention_months(): int
    {
        $settings = $this->get_settings();

        return (int) $settings['retention_months'];
    }

    public function get_retention_basis(): string
    {
        $settings = $this->get_settings();

        return $this->sanitize_retention_basis((string) $settings['retention_basis']);
    }

    private function sanitize_retention_basis(string $basis): string
    {
        $allowed = ['last_order', 'created_at', 'updated_at'];
        if (!in_array($basis, $allowed, true)) {
            return 'last_order';
        }

        return $basis;
    }
}
