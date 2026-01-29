<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\B2B\Admin\AdminPages;
use Bressol\Modules\B2B\Frontend\Endpoints;
use Bressol\Modules\B2B\Services\Capabilities;
use Bressol\Modules\B2B\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class B2BModule implements ModuleInterface
{
    public function register(): void
    {
        Installer::maybe_upgrade();
        (new Capabilities())->register();
        (new Settings())->ensure_defaults();

        $endpoints = new Endpoints();
        add_action('init', [$endpoints, 'register_rewrite']);
        add_filter('query_vars', [$endpoints, 'register_query_vars']);
        add_action('template_redirect', [$endpoints, 'handle_request']);
        add_action('admin_init', [$endpoints, 'maybe_flush_rewrite']);

        if (is_admin()) {
            $adminPages = new AdminPages();
            add_action('admin_menu', [$adminPages, 'registerMenus']);
        }
    }
}
