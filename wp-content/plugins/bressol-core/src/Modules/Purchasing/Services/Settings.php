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
            'purchasing_cost_ledger_sync_enabled' => false,
            'purchasing_planning_cycle_days' => 42,
            'purchasing_planning_reminder_lead_days' => 21,
            'purchasing_planning_next_shipment_date_utc' => '',
            'purchasing_planning_run_on_close_window' => false,
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

    public function is_purchasing_cost_ledger_sync_enabled(): bool
    {
        $settings = $this->get();
        return !empty($settings['purchasing_cost_ledger_sync_enabled']);
    }

    public function get_purchasing_planning_cycle_days(): int
    {
        $settings = $this->get();
        return max(1, (int) ($settings['purchasing_planning_cycle_days'] ?? 42));
    }

    public function get_purchasing_planning_reminder_lead_days(): int
    {
        $settings = $this->get();
        return max(1, (int) ($settings['purchasing_planning_reminder_lead_days'] ?? 21));
    }

    public function get_purchasing_planning_next_shipment_date_utc(): string
    {
        $settings = $this->get();
        return isset($settings['purchasing_planning_next_shipment_date_utc'])
            ? (string) $settings['purchasing_planning_next_shipment_date_utc']
            : '';
    }

    public function is_purchasing_planning_run_on_close_window(): bool
    {
        $settings = $this->get();
        return !empty($settings['purchasing_planning_run_on_close_window']);
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
        if (array_key_exists('purchasing_cost_ledger_sync_enabled', $payload)) {
            $settings['purchasing_cost_ledger_sync_enabled'] = (bool) $payload['purchasing_cost_ledger_sync_enabled'];
        }
        if (array_key_exists('purchasing_planning_cycle_days', $payload)) {
            $settings['purchasing_planning_cycle_days'] = max(1, (int) $payload['purchasing_planning_cycle_days']);
        }
        if (array_key_exists('purchasing_planning_reminder_lead_days', $payload)) {
            $settings['purchasing_planning_reminder_lead_days'] = max(1, (int) $payload['purchasing_planning_reminder_lead_days']);
        }
        if (array_key_exists('purchasing_planning_next_shipment_date_utc', $payload)) {
            $settings['purchasing_planning_next_shipment_date_utc'] = (string) $payload['purchasing_planning_next_shipment_date_utc'];
        }
        if (array_key_exists('purchasing_planning_run_on_close_window', $payload)) {
            $settings['purchasing_planning_run_on_close_window'] = (bool) $payload['purchasing_planning_run_on_close_window'];
        }

        update_option(self::OPTION_NAME, $settings, false);
    }
}
