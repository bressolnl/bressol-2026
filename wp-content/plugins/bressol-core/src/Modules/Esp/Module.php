<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Esp\Admin\AdminPages;
use Bressol\Modules\Esp\Services\Capabilities;
use Bressol\Modules\Esp\Services\SenderService;
use Bressol\Modules\Esp\Services\Settings;
use Bressol\Modules\Esp\Services\UnsubscribeService;

if (!defined('ABSPATH')) {
    exit;
}

final class Module implements ModuleInterface
{
    public const CRON_HOOK = 'bressol_esp_cron_send';

    public function register(): void
    {
        Installer::maybe_upgrade();
        (new Capabilities())->register();
        (new Settings())->ensure_defaults();

        add_action(self::CRON_HOOK, [$this, 'runQueueTick']);
        add_filter('cron_schedules', [$this, 'registerCronSchedule']);
        self::scheduleCron();
        add_action('phpmailer_init', [$this, 'configureMailer']);
        add_action('admin_post_nopriv_bressol_esp_unsub', [$this, 'handleUnsubscribe']);
        add_action('admin_post_bressol_esp_unsub', [$this, 'handleUnsubscribe']);

        if (is_admin()) {
            $adminPages = new AdminPages();
            add_action('admin_menu', [$adminPages, 'registerMenus']);
        }
    }

    public function runQueueTick(): void
    {
        (new SenderService())->run();
    }

    /** @return array<string, array<string, int|string>> */
    public function registerCronSchedule(array $schedules): array
    {
        if (!isset($schedules['bressol_every_minute'])) {
            $schedules['bressol_every_minute'] = [
                'interval' => 60,
                'display' => 'Bressol every minute',
            ];
        }
        return $schedules;
    }

    public static function scheduleCron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'bressol_every_minute', self::CRON_HOOK);
        }
    }

    public static function clearCron(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function configureMailer(\PHPMailer\PHPMailer\PHPMailer $phpmailer): void
    {
        // ESP aplica SMTP para marketing; transaccionales van por WooCommerce.
        (new Settings())->apply_smtp_settings($phpmailer);
    }

    public function handleUnsubscribe(): void
    {
        (new UnsubscribeService())->handle_request();
    }
}
