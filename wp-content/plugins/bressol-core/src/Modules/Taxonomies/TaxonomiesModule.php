<?php
declare(strict_types=1);

namespace Bressol\Modules\Taxonomies;

use Bressol\Core\ModuleInterface;

if (!defined('ABSPATH')) exit;

final class TaxonomiesModule implements ModuleInterface
{
    public function register(): void
    {
        add_action('init', [$this, 'registerTaxonomies']);
        add_action('admin_head', [$this, 'fixMomentColumnCss']);
    }

    public function registerTaxonomies(): void
    {
        register_taxonomy('bressol_moment', ['product'], [
            'labels' => [
                'name' => 'Momentos',
                'singular_name' => 'Momento',
            ],
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'hierarchical' => false, // tipo tag
            'rewrite' => ['slug' => 'moment'],
        ]);
    }

    public function fixMomentColumnCss(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'edit-product') {
            return;
        }

        echo '<style>
            .wp-list-table .column-taxonomy-bressol_moment { width: 180px; }
            .wp-list-table .column-taxonomy-bressol_moment a { white-space: nowrap; }
            .wp-list-table .column-taxonomy-bressol_moment { writing-mode: horizontal-tb !important; }
        </style>';
    }
}