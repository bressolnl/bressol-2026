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
        add_action('init', [$this, 'seed_admin_cap']);
    }

    public function seed_admin_cap(): void
    {
        if (!function_exists('wp_roles')) {
            return;
        }

        $role = wp_roles()->get_role('administrator');
        if ($role && !$role->has_cap(self::CAP)) {
            $role->add_cap(self::CAP);
        }
    }
}