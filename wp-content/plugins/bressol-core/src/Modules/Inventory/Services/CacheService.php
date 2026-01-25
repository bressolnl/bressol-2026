<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class CacheService
{
    private const VERSION_OPTION = 'bressol_inventory_pack_cache_version';

    /** @return array<string, mixed>|null */
    public function get_pack(int $packId): ?array
    {
        $key = $this->build_pack_key($packId);
        $value = get_transient($key);
        return is_array($value) ? $value : null;
    }

    /** @param array<string, mixed> $value */
    public function set_pack(int $packId, array $value, int $ttlSeconds): void
    {
        $key = $this->build_pack_key($packId);
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

    private function build_pack_key(int $packId): string
    {
        return 'bressol_inventory_pack_v' . $this->get_version() . '_' . $packId;
    }
}
