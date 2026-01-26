<?php
declare(strict_types=1);

namespace Bressol\Modules\Purchasing\Domain\Enum;

if (!defined('ABSPATH')) {
    exit;
}

final class Status
{
    public const DRAFT = 'draft';
    public const SENT = 'sent';
    public const CONFIRMED = 'confirmed';
    public const RECEIVING = 'receiving';
    public const CLOSED = 'closed';
    public const CANCELLED = 'cancelled';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::SENT,
            self::CONFIRMED,
            self::RECEIVING,
            self::CLOSED,
            self::CANCELLED,
        ];
    }
}
