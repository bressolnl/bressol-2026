<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

if (!defined('ABSPATH')) {
    exit;
}

interface PosMarketProviderInterface
{
    /** @return array<int, array{id:string,name:string,default_cost_cents:int}> */
    public function get_markets_for_pos(): array;
}
