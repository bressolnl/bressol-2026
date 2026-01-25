<?php
declare(strict_types=1);

namespace Bressol\Modules\Seo\Cli;

if (!defined('ABSPATH')) {
    exit;
}

final class SeoAuditCommand
{
    public function __invoke(): void
    {
        if (!class_exists('\WooCommerce')) {
            \WP_CLI::error('WooCommerce no disponible.');
        }

        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $rows = [];
        foreach ($query->posts as $productId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }

            $isPack = (string) get_post_meta($productId, '_bressol_pack_definition', true) !== '';
            if ($isPack) {
                continue;
            }

            $post = get_post($productId);
            $shortDescription = $post ? (string) $post->post_excerpt : '';
            $hasWeight = $shortDescription !== '' && preg_match('/\b\d+\s?(g|kg|ml|l)\b/i', $shortDescription);

            $intro = (string) get_post_meta($productId, 'bressol_pdp_intro', true);
            $faq = (string) get_post_meta($productId, 'bressol_pdp_faq', true);

            $smaakValues = [
                (string) get_post_meta($productId, 'bressol_smaak_textuur1', true),
                (string) get_post_meta($productId, 'bressol_smaak_textuur2', true),
                (string) get_post_meta($productId, 'bressol_smaak_textuur3', true),
                (string) get_post_meta($productId, 'bressol_smaak_ingredienten1', true),
                (string) get_post_meta($productId, 'bressol_smaak_ingredienten2', true),
                (string) get_post_meta($productId, 'bressol_smaak_ingredienten3', true),
                (string) get_post_meta($productId, 'bressol_smaak_karakter1', true),
                (string) get_post_meta($productId, 'bressol_smaak_karakter2', true),
                (string) get_post_meta($productId, 'bressol_smaak_karakter3', true),
            ];
            $hasSmaakprofiel = !empty(array_filter($smaakValues));

            $usageTips = (string) get_post_meta($productId, 'bressol_usage_tips', true);
            $usageImagesRaw = get_post_meta($productId, 'bressol_seo_usage_images', true);
            $usageImages = \Bressol\Modules\Seo\SeoMetaModule::normalizeImageIds($usageImagesRaw);
            $hasUsage = ($usageTips !== '') || !empty($usageImages);

            $quoteTitle = (string) get_post_meta($productId, 'bressol_seo_quote_title', true);
            $quoteText = (string) get_post_meta($productId, 'bressol_seo_quote_text', true);
            $hasQuote = ($quoteTitle !== '') || ($quoteText !== '');

            $missing = [];
            if (trim($shortDescription) === '') {
                $missing[] = 'excerpt';
            }
            if (!$hasWeight) {
                $missing[] = 'weight';
            }
            if (trim($intro) === '') {
                $missing[] = 'intro';
            }
            if (!$hasSmaakprofiel) {
                $missing[] = 'smaakprofiel';
            }
            if (!$hasUsage) {
                $missing[] = 'usage';
            }
            if (!$hasQuote) {
                $missing[] = 'quote';
            }
            if (trim($faq) === '') {
                $missing[] = 'faq';
            }

            if (empty($missing)) {
                continue;
            }

            $rows[] = [
                'id' => $productId,
                'title' => get_the_title($productId),
                'permalink' => get_permalink($productId),
                'missing' => implode(',', $missing),
            ];
        }

        if (empty($rows)) {
            \WP_CLI::success('Todos los productos NO pack cumplen el checklist.');
            return;
        }

        \WP_CLI::log('Productos NO pack con faltantes SEO:');
        \WP_CLI\Utils\format_items('table', $rows, ['id', 'title', 'permalink', 'missing']);
    }
}
