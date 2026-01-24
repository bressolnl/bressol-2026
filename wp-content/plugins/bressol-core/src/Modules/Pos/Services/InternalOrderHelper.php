<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class InternalOrderHelper
{
    public const META_KEY = '_bressol_internal_order';

    public static function is_internal_order($order): bool
    {
        $orderId = 0;
        if ($order instanceof \WC_Order) {
            $orderId = (int) $order->get_id();
        } elseif (is_numeric($order)) {
            $orderId = (int) $order;
        }

        if ($orderId <= 0) {
            return false;
        }

        $value = get_post_meta($orderId, self::META_KEY, true);
        return (string) $value === '1';
    }
}
