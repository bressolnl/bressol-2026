<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Services\Ports\Adapters;

use Bressol\Modules\Purchasing\Services\Ports\CostLedgerWritePort;

if (!defined('ABSPATH')) {
    exit;
}

final class CostLedgerAdapter implements CostLedgerWritePort
{
    private const SERVICE_CLASS = '\\Bressol\\Modules\\CostMargin\\Services\\CostLedgerService';

    public static function is_available(): bool
    {
        if (class_exists(self::SERVICE_CLASS)) {
            return true;
        }

        return has_action('bressol_cost_ledger_record_purchase') > 0;
    }

    public function record_purchase(array $payload): void
    {
        if (class_exists(self::SERVICE_CLASS)) {
            $service = new self::SERVICE_CLASS();
            if (method_exists($service, 'record_purchase')) {
                $service->record_purchase($payload);
                return;
            }
        }

        if (has_action('bressol_cost_ledger_record_purchase') > 0) {
            do_action('bressol_cost_ledger_record_purchase', $payload);
        }
    }
}
