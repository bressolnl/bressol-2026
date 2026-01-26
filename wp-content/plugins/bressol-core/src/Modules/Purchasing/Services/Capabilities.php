<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Capabilities
{
    private const BASE_CAPABILITY = 'manage_woocommerce';
    private const SENSITIVE_CAPABILITY = 'bressol_manage_purchasing';
    private const CAPS_SEEDED_OPTION = 'bressol_purchasing_caps_seeded';

    public function get_base_capability(): string
    {
        return class_exists('WooCommerce') ? self::BASE_CAPABILITY : 'manage_options';
    }

    public function get_sensitive_capability(): string
    {
        return self::SENSITIVE_CAPABILITY;
    }

    public function current_user_can_manage(): bool
    {
        return current_user_can($this->get_base_capability())
            && current_user_can($this->get_sensitive_capability());
    }

    public function current_user_can_sensitive(): bool
    {
        return current_user_can($this->get_sensitive_capability());
    }

    public static function ensure_caps_registered(): void
    {
        if (!is_admin() || !function_exists('wp_roles')) {
            return;
        }

        $seeded = get_option(self::CAPS_SEEDED_OPTION, '');
        if ($seeded === '1') {
            return;
        }

        $roles = wp_roles();
        if (!$roles) {
            return;
        }

        $role = $roles->get_role('administrator');
        if ($role && !$role->has_cap(self::SENSITIVE_CAPABILITY)) {
            $role->add_cap(self::SENSITIVE_CAPABILITY);
        }

        update_option(self::CAPS_SEEDED_OPTION, '1', false);
    }
}
