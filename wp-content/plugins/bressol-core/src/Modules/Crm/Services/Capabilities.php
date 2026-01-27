<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class Capabilities
{
    public const CAP = 'bressol_manage_crm';

    public function register(): void
    {
        add_action('admin_init', [$this, 'seed_admin_cap']);
    }

    public function seed_admin_cap(): void
    {
        if (!function_exists('wp_roles')) {
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
}
