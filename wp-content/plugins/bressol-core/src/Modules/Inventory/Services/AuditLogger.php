<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Services;

use Bressol\Modules\Crm\Services\AuditLogger as CrmAuditLogger;

if (!defined('ABSPATH')) {
    exit;
}

final class AuditLogger
{
    private ?CrmAuditLogger $logger;

    public function __construct()
    {
        $this->logger = class_exists(CrmAuditLogger::class) ? new CrmAuditLogger() : null;
    }

    /** @param array<string, mixed> $context */
    public function log(string $action, array $context = []): void
    {
        if (!$this->logger) {
            return;
        }

        $this->logger->log($action, 'inventory', null, get_current_user_id(), $context);
    }
}
