<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Lots\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

final class LotMoveRepository
{
    private const TABLE = 'bressol_lot_moves';

    public function add_move(
        int $lotId,
        string $type,
        int $qty,
        string $refType,
        string $refId,
        ?string $note = null
    ): int {
        if ($lotId <= 0) {
            return 0;
        }

        $type = sanitize_key($type);
        $refType = sanitize_key($refType);
        $refId = trim($refId);

        if ($qty === 0 && $type !== 'cogs_adjust') {
            return 0;
        }

        if (!in_array($type, ['receipt', 'consume', 'adjust', 'cogs_adjust'], true)) {
            return 0;
        }

        if (!in_array($refType, ['transfer', 'order', 'manual'], true) || $refId === '') {
            return 0;
        }

        global $wpdb;

        $payload = [
            'lot_id' => $lotId,
            'type' => $type,
            'qty' => $qty,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_at' => current_time('mysql'),
            'note' => $note !== null ? (string) $note : null,
        ];

        $inserted = $wpdb->insert($this->table(), $payload, [
            '%d',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
        ]);

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }
}
