<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class PosMarketsCatalog
{
    private PosMarketProviderInterface $provider;
    private PosSettings $settings;

    public function __construct(PosMarketProviderInterface $provider, ?PosSettings $settings = null)
    {
        $this->provider = $provider;
        $this->settings = $settings ?? new PosSettings();
    }

    /** @return array<int, array{id:string,name:string,default_cost_cents:int}> */
    public function list(): array
    {
        $markets = $this->provider->get_markets_for_pos();

        usort($markets, static function (array $left, array $right): int {
            return strcmp((string) $left['name'], (string) $right['name']);
        });

        return $markets;
    }
}
