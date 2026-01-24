<?php
declare(strict_types=1);

namespace Bressol\Modules\MarketsEvents\Integrations;

use Bressol\Modules\MarketsEvents\Services\OrderEventMetaService;

if (!defined('ABSPATH')) {
    exit;
}

final class WooCommerceHooks
{
    private OrderEventMetaService $metaService;

    public function __construct(OrderEventMetaService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function register(): void
    {
        add_action('woocommerce_new_order', [$this, 'handle_new_order'], 10, 1);
    }

    public function handle_new_order(int $orderId): void
    {
        $this->metaService->maybe_set_on_checkout_or_pos($orderId, get_current_user_id());
    }
}
