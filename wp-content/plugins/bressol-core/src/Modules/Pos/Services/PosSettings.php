<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class PosSettings
{
    private const OPTION_NAME = 'bressol_pos_settings';

    /** @return array<string, mixed> */
    public function get_defaults(): array
    {
        return [
            'order_status_default' => 'completed',
            'currency' => $this->get_default_currency(),
            'points_value_cents' => 5,
            'min_redemption_points' => 20,
            'max_redemption_percent_of_order' => 50,
            'markets' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function get_settings(): array
    {
        $settings = get_option(self::OPTION_NAME, null);
        if ($settings === null) {
            $defaults = $this->get_defaults();
            add_option(self::OPTION_NAME, $defaults, '', 'no');
            return $defaults;
        }

        if (!is_array($settings)) {
            $settings = [];
        }

        $defaults = $this->get_defaults();
        $merged = array_merge($defaults, $settings);

        $merged['order_status_default'] = $this->sanitize_order_status((string) $merged['order_status_default']);
        $merged['currency'] = $this->sanitize_currency((string) $merged['currency']);
        $merged['points_value_cents'] = max(1, (int) $merged['points_value_cents']);
        $merged['min_redemption_points'] = max(0, (int) $merged['min_redemption_points']);
        $merged['max_redemption_percent_of_order'] = $this->sanitize_percent((int) $merged['max_redemption_percent_of_order']);
        $merged['markets'] = $this->sanitize_markets($merged['markets']);

        return $merged;
    }

    /** @param array<string, mixed> $input */
    public function update_settings(array $input): void
    {
        $settings = $this->get_settings();

        if (isset($input['order_status_default'])) {
            $settings['order_status_default'] = $this->sanitize_order_status((string) $input['order_status_default']);
        }

        if (isset($input['currency'])) {
            $settings['currency'] = $this->sanitize_currency((string) $input['currency']);
        }

        if (isset($input['points_value_cents'])) {
            $settings['points_value_cents'] = max(1, (int) $input['points_value_cents']);
        }

        if (isset($input['min_redemption_points'])) {
            $settings['min_redemption_points'] = max(0, (int) $input['min_redemption_points']);
        }

        if (isset($input['max_redemption_percent_of_order'])) {
            $settings['max_redemption_percent_of_order'] = $this->sanitize_percent((int) $input['max_redemption_percent_of_order']);
        }

        if (isset($input['markets'])) {
            $settings['markets'] = $this->sanitize_markets($input['markets']);
        }

        update_option(self::OPTION_NAME, $settings);
    }

    public function get_order_status_default(): string
    {
        $settings = $this->get_settings();

        return (string) $settings['order_status_default'];
    }

    public function get_currency(): string
    {
        $settings = $this->get_settings();

        return (string) $settings['currency'];
    }

    public function get_points_value_cents(): int
    {
        $settings = $this->get_settings();

        return (int) $settings['points_value_cents'];
    }

    public function get_min_redemption_points(): int
    {
        $settings = $this->get_settings();

        return (int) $settings['min_redemption_points'];
    }

    public function get_max_redemption_percent_of_order(): int
    {
        $settings = $this->get_settings();

        return (int) $settings['max_redemption_percent_of_order'];
    }

    /** @return array<int, array<string, mixed>> */
    public function get_markets(): array
    {
        $settings = $this->get_settings();

        return $this->sanitize_markets($settings['markets']);
    }

    /** @param array<int, array<string, mixed>> $markets */
    public function set_markets(array $markets): void
    {
        $settings = $this->get_settings();
        $settings['markets'] = $this->sanitize_markets($markets);
        update_option(self::OPTION_NAME, $settings);
    }

    public function add_market(string $name, bool $active, int $defaultCostCents): string
    {
        $markets = $this->get_markets();
        $id = $this->generate_market_id($markets);

        $markets[] = [
            'id' => $id,
            'name' => $this->sanitize_market_name($name),
            'active' => $active,
            'default_cost_cents' => max(0, $defaultCostCents),
        ];

        $this->set_markets($markets);

        return $id;
    }

    public function update_market(string $id, string $name, bool $active, int $defaultCostCents): bool
    {
        $markets = $this->get_markets();
        $updated = false;

        foreach ($markets as $index => $market) {
            if ((string) ($market['id'] ?? '') !== $id) {
                continue;
            }

            $markets[$index] = [
                'id' => $id,
                'name' => $this->sanitize_market_name($name),
                'active' => $active,
                'default_cost_cents' => max(0, $defaultCostCents),
            ];
            $updated = true;
            break;
        }

        if ($updated) {
            $this->set_markets($markets);
        }

        return $updated;
    }

    public function set_market_active(string $id, bool $active): bool
    {
        $markets = $this->get_markets();
        $updated = false;

        foreach ($markets as $index => $market) {
            if ((string) ($market['id'] ?? '') !== $id) {
                continue;
            }

            $markets[$index]['active'] = $active;
            $updated = true;
            break;
        }

        if ($updated) {
            $this->set_markets($markets);
        }

        return $updated;
    }

    /** @return array<string, string> */
    public function get_allowed_order_statuses(): array
    {
        $default = [
            'completed' => 'Completed',
            'processing' => 'Processing',
            'pending' => 'Pending',
            'on-hold' => 'On hold',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'failed' => 'Failed',
        ];

        if (!function_exists('wc_get_order_statuses')) {
            return $default;
        }

        $statuses = wc_get_order_statuses();
        $mapped = [];
        foreach ($statuses as $key => $label) {
            $slug = str_starts_with($key, 'wc-') ? substr($key, 3) : $key;
            $mapped[$slug] = $label;
        }

        return $mapped ?: $default;
    }

    private function sanitize_order_status(string $status): string
    {
        $status = sanitize_key($status);
        $allowed = array_keys($this->get_allowed_order_statuses());
        if (!in_array($status, $allowed, true)) {
            return 'completed';
        }

        return $status;
    }

    private function sanitize_currency(string $currency): string
    {
        $currency = strtoupper(sanitize_text_field($currency));
        if ($currency === '' || !preg_match('/^[A-Z]{3}$/', $currency)) {
            return $this->get_default_currency();
        }

        return $currency;
    }

    private function sanitize_percent(int $value): int
    {
        return max(0, min(100, $value));
    }

    /** @param mixed $markets */
    private function sanitize_markets($markets): array
    {
        if (!is_array($markets)) {
            return [];
        }

        $sanitized = [];
        foreach ($markets as $market) {
            if (!is_array($market)) {
                continue;
            }

            $id = isset($market['id']) ? sanitize_key((string) $market['id']) : '';
            $name = isset($market['name']) ? $this->sanitize_market_name((string) $market['name']) : '';
            if ($id === '' || $name === '') {
                continue;
            }

            $sanitized[] = [
                'id' => $id,
                'name' => $name,
                'active' => !empty($market['active']),
                'default_cost_cents' => max(0, (int) ($market['default_cost_cents'] ?? 0)),
            ];
        }

        return $sanitized;
    }

    /** @param array<int, array<string, mixed>> $markets */
    private function generate_market_id(array $markets): string
    {
        $existing = [];
        foreach ($markets as $market) {
            if (!empty($market['id'])) {
                $existing[(string) $market['id']] = true;
            }
        }

        for ($i = 0; $i < 5; $i++) {
            $candidate = $this->make_market_id();
            if (!isset($existing[$candidate])) {
                return $candidate;
            }
        }

        return $this->make_market_id();
    }

    private function make_market_id(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return sanitize_key(wp_generate_uuid4());
        }

        if (function_exists('random_bytes')) {
            return sanitize_key('market_' . bin2hex(random_bytes(8)));
        }

        return sanitize_key('market_' . uniqid('', true));
    }

    private function sanitize_market_name(string $name): string
    {
        return trim(sanitize_text_field($name));
    }

    private function get_default_currency(): string
    {
        if (function_exists('get_woocommerce_currency')) {
            $currency = (string) get_woocommerce_currency();
            if ($currency !== '') {
                return $currency;
            }
        }

        return 'EUR';
    }
}
