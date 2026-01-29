<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    private const OPTION_NAME = 'bressol_b2b_settings';

    /** @return array<string, mixed> */
    public function get_settings(): array
    {
        $defaults = [
            'catalog_pdf_relpath' => 'b2b/catalog.pdf',
            'pricelist_pdf_relpath' => 'b2b/pricelist.pdf',
            'token_ttl_days' => 30,
            'sales_owner_user_id_1' => 0,
            'sales_owner_user_id_2' => 0,
            'last_assigned_owner_user_id' => 0,
            'tier_options' => ['wholesale', 'horeca', 'corporate', 'other'],
        ];

        $stored = get_option(self::OPTION_NAME, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge($defaults, $stored);
    }

    public function ensure_defaults(): void
    {
        $settings = $this->get_settings();
        update_option(self::OPTION_NAME, $settings, false);
    }

    public function get_token_ttl_days(): int
    {
        $settings = $this->get_settings();
        $ttl = isset($settings['token_ttl_days']) ? (int) $settings['token_ttl_days'] : 30;
        return max(1, $ttl);
    }

    /** @return string[] */
    public function get_tier_options(): array
    {
        $settings = $this->get_settings();
        $tiers = $settings['tier_options'] ?? [];
        if (!is_array($tiers)) {
            $tiers = [];
        }
        $normalized = [];
        foreach ($tiers as $tier) {
            $value = sanitize_key((string) $tier);
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
        }
        if ($normalized === []) {
            $normalized = ['wholesale', 'horeca', 'corporate', 'other'];
        }
        return array_values(array_unique($normalized));
    }

    public function get_default_owner_user_id(): int
    {
        $settings = $this->get_settings();
        $owner1 = $this->normalize_owner_user_id($settings['sales_owner_user_id_1'] ?? 0);
        if ($owner1 > 0) {
            return $owner1;
        }

        $admins = get_users([
            'role' => 'administrator',
            'fields' => ['ID'],
            'number' => 1,
        ]);
        if (!empty($admins)) {
            return (int) $admins[0]->ID;
        }

        return 0;
    }

    public function get_next_sales_owner_user_id(): int
    {
        $settings = $this->get_settings();
        $owner1 = $this->normalize_owner_user_id($settings['sales_owner_user_id_1'] ?? 0);
        $owner2 = $this->normalize_owner_user_id($settings['sales_owner_user_id_2'] ?? 0);
        $last = isset($settings['last_assigned_owner_user_id']) ? (int) $settings['last_assigned_owner_user_id'] : 0;

        $candidate = 0;
        if ($owner1 > 0 && $owner2 > 0) {
            $candidate = $last === $owner1 ? $owner2 : $owner1;
        } elseif ($owner1 > 0) {
            $candidate = $owner1;
        } elseif ($owner2 > 0) {
            $candidate = $owner2;
        }

        if ($candidate > 0) {
            $this->persist_last_assigned_owner($candidate, $last);
        }

        return $candidate > 0 ? $candidate : $this->get_default_owner_user_id();
    }

    /** @param array<string, mixed> $payload */
    public function update(array $payload): void
    {
        $settings = $this->get_settings();

        if (array_key_exists('catalog_pdf_relpath', $payload)) {
            $settings['catalog_pdf_relpath'] = ltrim(sanitize_text_field((string) $payload['catalog_pdf_relpath']), '/');
        }
        if (array_key_exists('pricelist_pdf_relpath', $payload)) {
            $settings['pricelist_pdf_relpath'] = ltrim(sanitize_text_field((string) $payload['pricelist_pdf_relpath']), '/');
        }
        if (array_key_exists('token_ttl_days', $payload)) {
            $settings['token_ttl_days'] = max(1, (int) $payload['token_ttl_days']);
        }
        if (array_key_exists('sales_owner_user_id_1', $payload)) {
            $settings['sales_owner_user_id_1'] = $this->normalize_owner_user_id($payload['sales_owner_user_id_1'] ?? 0);
        }
        if (array_key_exists('sales_owner_user_id_2', $payload)) {
            $settings['sales_owner_user_id_2'] = $this->normalize_owner_user_id($payload['sales_owner_user_id_2'] ?? 0);
        }
        if (array_key_exists('tier_options', $payload) && is_array($payload['tier_options'])) {
            $settings['tier_options'] = $payload['tier_options'];
        }

        update_option(self::OPTION_NAME, $settings, false);
    }

    private function normalize_owner_user_id($value): int
    {
        $id = max(0, (int) $value);
        if ($id <= 0) {
            return 0;
        }
        return get_userdata($id) ? $id : 0;
    }

    private function persist_last_assigned_owner(int $candidate, int $expectedLast): void
    {
        $stored = get_option(self::OPTION_NAME, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $currentLast = isset($stored['last_assigned_owner_user_id']) ? (int) $stored['last_assigned_owner_user_id'] : 0;
        if ($currentLast !== $expectedLast) {
            return;
        }
        $stored['last_assigned_owner_user_id'] = $candidate;
        update_option(self::OPTION_NAME, $stored, false);
    }
}
