<?php
declare(strict_types=1);

namespace Bressol\Modules\Products;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Products\Admin\ProductsImportPage;
use Bressol\Modules\Products\Cli\ProductsBackfillFlagsCommand;
use Bressol\Modules\Products\Cli\ProductsImportCommand;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductsModule implements ModuleInterface
{
    public function register(): void
    {
        if (defined('WP_CLI') && WP_CLI && class_exists('\\WP_CLI')) {
            \WP_CLI::add_command('bressol products import', new ProductsImportCommand());
            \WP_CLI::add_command('bressol products backfill-flags', new ProductsBackfillFlagsCommand());
        }

        if (is_admin()) {
            (new ProductsImportPage())->register();
        }
    }
}
