<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Capabilities
{
    public const CAP = 'bressol_manage_esp';

    public function register(): void
    {
        add_action('admin_init', [$this, 'seed_admin_cap']);
        add_filter('map_meta_cap', [$this, 'map_meta_cap'], 10, 4);
    }

    public function seed_admin_cap(): void
    {
        if (!is_admin() || !function_exists('wp_roles')) {
            return;
        }

        $roles = wp_roles();
        if (!$roles) {
            return;
        }

        $role = $roles->get_role('administrator');
        if ($role && !$role->has_cap(self::CAP)) {
            $role->add_cap(self::CAP);
        }
    }

    /** @param array<int, string> $caps */
    public function map_meta_cap(array $caps, string $cap, int $userId, array $args): array
    {
        if ($cap !== self::CAP) {
            return $caps;
        }

        if (user_can($userId, 'manage_options') || user_can($userId, 'manage_woocommerce')) {
            return [];
        }

        return ['do_not_allow'];
    }
}
