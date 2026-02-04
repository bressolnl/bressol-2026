<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\MarketsEvents\Admin\AdminModule;
use Bressol\Modules\MarketsEvents\Frontend\UpcomingEventsShortcode;
use Bressol\Modules\MarketsEvents\Integrations\WooCommerceHooks;
use Bressol\Modules\MarketsEvents\Services\Capabilities;
use Bressol\Modules\MarketsEvents\Repositories\EventRepository;
use Bressol\Modules\MarketsEvents\Services\EventService;
use Bressol\Modules\MarketsEvents\Services\OrderEventMetaService;
use Bressol\Modules\MarketsEvents\Services\PosEventContextService;
use Bressol\Modules\MarketsEvents\Services\EventCompletionService;

if (!defined('ABSPATH')) {
    exit;
}

final class MarketsEventsModule implements ModuleInterface
{
    public function register(): void
    {
        add_action('admin_init', [Installer::class, 'maybe_upgrade']);
        add_action('admin_init', [Capabilities::class, 'ensure_caps_registered']);
        (new EventCompletionService())->register();
        EventCompletionService::schedule();

        (new UpcomingEventsShortcode())->register();

        if (defined('WP_CLI') && WP_CLI) {
            Installer::maybe_upgrade();
        }

        if (is_admin()) {
            $adminModule = new AdminModule(static function (): \Bressol\Modules\MarketsEvents\Admin\AdminPages {
                return new \Bressol\Modules\MarketsEvents\Admin\AdminPages(
                    new EventRepository(),
                    new EventService(),
                    new Capabilities()
                );
            });
            $adminModule->register();
        }

        add_action('plugins_loaded', static function (): void {
            if (!class_exists('WooCommerce')) {
                return;
            }

            $hooks = new WooCommerceHooks(
                new OrderEventMetaService(new PosEventContextService())
            );
            $hooks->register();
        });
    }
}
