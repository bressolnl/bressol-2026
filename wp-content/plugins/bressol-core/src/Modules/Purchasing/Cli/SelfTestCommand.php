<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Cli;

use Bressol\Modules\Purchasing\Services\Capabilities;
use Bressol\Modules\Purchasing\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class SelfTestCommand
{
    private Capabilities $capabilities;
    private Settings $settings;

    public function __construct(Capabilities $capabilities, Settings $settings)
    {
        $this->capabilities = $capabilities;
        $this->settings = $settings;
    }

    /**
     * Selftest del scaffold de Purchasing.
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        \WP_CLI::log('OK scaffold purchasing');
        \WP_CLI::log('Caps: base=' . $this->capabilities->get_base_capability() . ' sensitive=' . $this->capabilities->get_sensitive_capability());

        $settings = $this->settings->get();
        \WP_CLI::log('Settings: purchasing_enabled=' . ($settings['purchasing_enabled'] ? 'true' : 'false'));
        \WP_CLI::log('Settings: purchasing_cron_enabled=' . ($settings['purchasing_cron_enabled'] ? 'true' : 'false'));
        \WP_CLI::log('Settings: purchase_planning_enabled=' . ($settings['purchase_planning_enabled'] ? 'true' : 'false'));
    }
}
