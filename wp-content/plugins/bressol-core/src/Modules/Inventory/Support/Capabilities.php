<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class Capabilities
{
    private const BASE_CAPABILITY = 'manage_woocommerce';

    public function get_base_capability(): string
    {
        return class_exists('WooCommerce') ? self::BASE_CAPABILITY : 'manage_options';
    }
}
