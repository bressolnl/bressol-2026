<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services\Ports;

if (!defined('ABSPATH')) {
    exit;
}

interface LeadTimePort
{
    public function get_lead_time_days(): ?int;
}
