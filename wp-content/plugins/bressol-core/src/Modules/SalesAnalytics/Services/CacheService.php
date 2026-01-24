<?php
declare(strict_types=1);

namespace Bressol\Modules\SalesAnalytics\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class CacheService
{
    private const VERSION_OPTION = 'bressol_sales_analytics_cache_version';

    /** @return array<string, mixed>|null */
    public function get(string $type, array $filters): ?array
    {
        $key = $this->build_cache_key($type, $filters);
        $value = get_transient($key);
        return is_array($value) ? $value : null;
    }

    /** @param array<string, mixed> $value */
    public function set(string $type, array $filters, array $value, int $ttlSeconds): void
    {
        $key = $this->build_cache_key($type, $filters);
        set_transient($key, $value, $ttlSeconds);
    }

    public function bump_version(): void
    {
        $version = $this->get_version();
        update_option(self::VERSION_OPTION, $version + 1, false);
    }

    public function get_version(): int
    {
        $version = get_option(self::VERSION_OPTION, 1);
        return is_numeric($version) ? (int) $version : 1;
    }

    private function build_cache_key(string $type, array $filters): string
    {
        $normalized = FiltersNormalizer::normalize($filters);
        $payload = [
            'type' => $type,
            'filters' => $normalized,
        ];
        $hash = sha1(wp_json_encode($payload));

        return 'bressol_sa_v' . $this->get_version() . '_' . $hash;
    }
}
