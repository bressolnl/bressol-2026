<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    private const OPTION_NAME = 'bressol_purchasing_settings';

    /** @return array<string, mixed> */
    public function get(): array
    {
        $defaults = [
            'purchasing_enabled' => false,
            'purchasing_cron_enabled' => false,
            'purchase_planning_enabled' => false,
            'purchasing_stock_sync_enabled' => false,
        ];

        $stored = get_option(self::OPTION_NAME, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge($defaults, $stored);
    }

    public function is_purchasing_enabled(): bool
    {
        $settings = $this->get();
        return !empty($settings['purchasing_enabled']);
    }

    public function is_purchasing_cron_enabled(): bool
    {
        $settings = $this->get();
        return !empty($settings['purchasing_cron_enabled']);
    }

    public function is_purchase_planning_enabled(): bool
    {
        $settings = $this->get();
        return !empty($settings['purchase_planning_enabled']);
    }

    public function is_purchasing_stock_sync_enabled(): bool
    {
        $settings = $this->get();
        return !empty($settings['purchasing_stock_sync_enabled']);
    }

    public function update(array $payload): void
    {
        $settings = $this->get();
        if (array_key_exists('purchasing_enabled', $payload)) {
            $settings['purchasing_enabled'] = (bool) $payload['purchasing_enabled'];
        }
        if (array_key_exists('purchasing_cron_enabled', $payload)) {
            $settings['purchasing_cron_enabled'] = (bool) $payload['purchasing_cron_enabled'];
        }
        if (array_key_exists('purchase_planning_enabled', $payload)) {
            $settings['purchase_planning_enabled'] = (bool) $payload['purchase_planning_enabled'];
        }
        if (array_key_exists('purchasing_stock_sync_enabled', $payload)) {
            $settings['purchasing_stock_sync_enabled'] = (bool) $payload['purchasing_stock_sync_enabled'];
        }

        update_option(self::OPTION_NAME, $settings, false);
    }
}
