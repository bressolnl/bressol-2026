<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Capabilities
{
    private const BASE_CAPABILITY = 'manage_woocommerce';
    private const EVENTS_CAPABILITY = 'bressol_manage_events';

    public function get_base_capability(): string
    {
        return class_exists('WooCommerce') ? self::BASE_CAPABILITY : 'manage_options';
    }

    public function get_events_capability(): string
    {
        return self::EVENTS_CAPABILITY;
    }

    public static function ensure_caps_registered(): void
    {
        if (!is_admin() || !function_exists('wp_roles')) {
            return;
        }

        $roles = wp_roles();
        if (!$roles) {
            return;
        }

        $role = $roles->get_role('administrator');
        if ($role && !$role->has_cap(self::EVENTS_CAPABILITY)) {
            $role->add_cap(self::EVENTS_CAPABILITY);
        }
    }
}
