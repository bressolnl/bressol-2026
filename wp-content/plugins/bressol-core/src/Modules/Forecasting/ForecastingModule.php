<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Forecasting\Admin\AdminPages;
use Bressol\Modules\Forecasting\Admin\DiagnosticsPage;
use Bressol\Modules\Forecasting\Cli\SelfTestCommand;

if (!defined('ABSPATH')) {
    exit;
}

final class ForecastingModule implements ModuleInterface
{
    public function register(): void
    {
        add_action('admin_init', [Installer::class, 'maybe_upgrade']);

        if (is_admin()) {
            $adminPages = new AdminPages();
            add_action('admin_menu', [$adminPages, 'registerMenus']);
            $diagnosticsPage = new DiagnosticsPage();
            add_action('admin_post_bressol_forecasting_selftest', [$diagnosticsPage, 'handleSelftest']);
            add_action('admin_post_bressol_forecasting_run', [$diagnosticsPage, 'handleRunForecast']);
            add_action('admin_post_bressol_forecasting_load_snapshot', [$diagnosticsPage, 'handleLoadSnapshot']);
            add_action('admin_post_bressol_forecasting_save_snapshot', [$diagnosticsPage, 'handleSaveSnapshot']);
            add_action('admin_post_bressol_forecasting_generate_pos_snapshot', [$diagnosticsPage, 'handleGeneratePosSnapshot']);
            add_action('admin_post_bressol_forecasting_backfill_pos_snapshots', [$diagnosticsPage, 'handleBackfillPosSnapshots']);
            add_action('admin_post_bressol_forecasting_pos_snapshot_debug', [$diagnosticsPage, 'handlePosSnapshotDebug']);
        }

        if (defined('WP_CLI') && WP_CLI && class_exists('\\WP_CLI')) {
            Installer::maybe_upgrade();
            \WP_CLI::add_command('bressol forecast selftest', new SelfTestCommand());
        }
    }
}
