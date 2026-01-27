<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Esp\Admin\AdminPages;
use Bressol\Modules\Esp\Services\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

final class Module implements ModuleInterface
{
    public const CRON_HOOK = 'bressol_esp_queue_tick';

    public function register(): void
    {
        Installer::maybe_upgrade();
        (new Capabilities())->register();

        add_action(self::CRON_HOOK, [$this, 'runQueueTick']);

        if (is_admin()) {
            $adminPages = new AdminPages();
            add_action('admin_menu', [$adminPages, 'registerMenus']);
        }
    }

    public function runQueueTick(): void
    {
        // Placeholder cron job. Keep idempotent.
    }

    public static function scheduleCron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
        }
    }

    public static function clearCron(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
