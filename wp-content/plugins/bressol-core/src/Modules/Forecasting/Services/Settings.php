<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    private const OPTION_NAME = 'bressol_forecasting_settings';

    /** @return array<string, mixed> */
    public function get(): array
    {
        $defaults = [
            'forecasting_enabled' => false,
        ];

        $stored = get_option(self::OPTION_NAME, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge($defaults, $stored);
    }

    public function is_enabled(): bool
    {
        $settings = $this->get();
        return !empty($settings['forecasting_enabled']);
    }

    /** @param array<string, mixed> $payload */
    public function update(array $payload): void
    {
        $settings = $this->get();
        if (array_key_exists('forecasting_enabled', $payload)) {
            $settings['forecasting_enabled'] = (bool) $payload['forecasting_enabled'];
        }

        update_option(self::OPTION_NAME, $settings, false);
    }
}
