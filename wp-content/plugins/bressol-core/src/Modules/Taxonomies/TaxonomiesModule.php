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
        add_action('init', [$this, 'registerMeta']);
        add_action('admin_head', [$this, 'fixMomentColumnCss']);

        if (is_admin()) {
            (new \Bressol\Modules\Taxonomies\Admin\TermSeoFields())->register();
        }
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

    public function registerMeta(): void
    {
        $common = [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ];

        register_term_meta('product_cat', 'bressol_cat_intro', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeHtml'],
            // Only allow users who can edit this term.
            'auth_callback' => [$this, 'authTermMeta'],
        ]));
        register_term_meta('product_cat', 'bressol_cat_longform', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeHtml'],
            // Only allow users who can edit this term.
            'auth_callback' => [$this, 'authTermMeta'],
        ]));
        register_term_meta('product_cat', 'bressol_cat_faq', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeJson'],
            // Only allow users who can edit this term.
            'auth_callback' => [$this, 'authTermMeta'],
        ]));

        register_term_meta('bressol_moment', 'bressol_moment_intro', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeHtml'],
            // Only allow users who can edit this term.
            'auth_callback' => [$this, 'authTermMeta'],
        ]));
        register_term_meta('bressol_moment', 'bressol_moment_longform', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeHtml'],
            // Only allow users who can edit this term.
            'auth_callback' => [$this, 'authTermMeta'],
        ]));
        register_term_meta('bressol_moment', 'bressol_moment_faq', array_merge($common, [
            'sanitize_callback' => [$this, 'sanitizeJson'],
            // Only allow users who can edit this term.
            'auth_callback' => [$this, 'authTermMeta'],
        ]));
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

    private function sanitizeHtml($value): string
    {
        return wp_kses_post((string) $value);
    }

    private function sanitizeJson($value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }
        return wp_json_encode($decoded);
    }

    private function authTermMeta($allowed = false, $metaKey = '', $termId = 0, $userId = null, $cap = null, $args = null): bool
    {
        return current_user_can('edit_term', (int) $termId);
    }
}