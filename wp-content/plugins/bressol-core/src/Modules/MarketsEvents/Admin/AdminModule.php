<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminModule
{
    /** @var callable */
    private $adminPagesFactory;

    public function __construct(callable $adminPagesFactory)
    {
        $this->adminPagesFactory = $adminPagesFactory;
    }

    public function register(): void
    {
        add_action('admin_menu', function (): void {
            $adminPages = ($this->adminPagesFactory)();
            if ($adminPages instanceof AdminPages) {
                $adminPages->registerMenus();
            }
        });
    }
}
