<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Crm\Admin\AdminPages;
use Bressol\Modules\Crm\Services\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

final class Module implements ModuleInterface
{
    public const CRON_HOOK = 'bressol_crm_daily';

    public function register(): void
    {
        Installer::maybe_upgrade();
        (new Capabilities())->register();

        add_action(self::CRON_HOOK, [$this, 'runDailyCron']);

        if (is_admin()) {
            $adminPages = new AdminPages();
            add_action('admin_menu', [$adminPages, 'registerMenus']);
        }
    }

    public function runDailyCron(): void
    {
        // Placeholder cron job. Keep idempotent.
    }

    public static function scheduleCron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'daily', self::CRON_HOOK);
        }
    }

    public static function clearCron(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
