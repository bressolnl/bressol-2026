<?php
declare(strict_types=1);

namespace Bressol\Modules\Forecasting\Services\Ports;

use Bressol\Modules\Purchasing\Repositories\SupplierRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class PurchasingLeadTimePort implements LeadTimePort
{
    public function get_lead_time_days(): ?int
    {
        if (!class_exists(SupplierRepository::class)) {
            return null;
        }

        $repo = new SupplierRepository();
        $suppliers = $repo->list_suppliers(2, 0, '');
        if (!is_array($suppliers) || $suppliers === []) {
            return null;
        }

        if (count($suppliers) !== 1) {
            return null;
        }

        $lead = isset($suppliers[0]['lead_time_days']) ? (int) $suppliers[0]['lead_time_days'] : null;
        return $lead !== null ? max(0, $lead) : null;
    }
}
