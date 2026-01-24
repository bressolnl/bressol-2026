<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    private const OPTION_NAME = 'bressol_sales_analytics_settings';

    /** @return array<string, mixed> */
    public function get(): array
    {
        $defaults = [
            'export_pii_enabled' => false,
        ];

        $stored = get_option(self::OPTION_NAME, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge($defaults, $stored);
    }

    public function is_pii_export_enabled(): bool
    {
        $settings = $this->get();
        return !empty($settings['export_pii_enabled']);
    }

    public function update(array $payload): void
    {
        $settings = $this->get();
        if (array_key_exists('export_pii_enabled', $payload)) {
            $settings['export_pii_enabled'] = (bool) $payload['export_pii_enabled'];
        }

        update_option(self::OPTION_NAME, $settings, false);
    }
}
