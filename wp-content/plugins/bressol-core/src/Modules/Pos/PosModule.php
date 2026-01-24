<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Crm\Services\AuditLogger;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Crm\Services\PointsService;
use Bressol\Modules\Crm\Services\Settings as CrmSettings;
use Bressol\Modules\Pos\Admin\AdminPages;
use Bressol\Modules\Pos\Services\CustomerLookupService;
use Bressol\Modules\Pos\Services\PosSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class PosModule implements ModuleInterface
{
    private const CRON_HOOK = 'bressol_pos_cleanup_coupons';
    private const DEFAULT_COUPON_RETENTION_DAYS = 90;

    /** @var string[] */
    private const PAGE_SLUGS = [
        'bressol-pos',
        'bressol-pos-customers',
        'bressol-pos-settings',
        'bressol-pos-reports',
    ];

    public function register(): void
    {
        add_action(self::CRON_HOOK, [$this, 'cleanup_pos_coupons']);

        if (!is_admin()) {
            return;
        }

        $settings = new PosSettings();
        $auditLogger = class_exists(AuditLogger::class) ? new AuditLogger() : null;
        $customerService = new CustomerService($auditLogger);
        $lookupService = new CustomerLookupService($customerService, $auditLogger);
        $adminPages = new AdminPages($settings, $customerService, $lookupService);

        add_action('admin_menu', [$adminPages, 'registerMenus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_ajax_bressol_pos_find_customer', [$this, 'handleFindCustomerAjax']);
        add_action('wp_ajax_bressol_pos_search_products', [$this, 'handleSearchProductsAjax']);
        add_action('wp_ajax_bressol_pos_create_order', [$this, 'handleCreateOrderAjax']);
    }

    public function enqueueAdminAssets(string $hook): void
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (!in_array($page, self::PAGE_SLUGS, true)) {
            return;
        }

        $scriptSrc = plugins_url(
            'src/Modules/Pos/assets/pos.js',
            WP_PLUGIN_DIR . '/bressol-core/bressol-core.php'
        );
        $styleSrc = plugins_url(
            'src/Modules/Pos/assets/pos.css',
            WP_PLUGIN_DIR . '/bressol-core/bressol-core.php'
        );

        wp_enqueue_script('bressol-pos-admin', $scriptSrc, [], '0.1.0', true);
        wp_enqueue_style('bressol-pos-admin', $styleSrc, [], '0.1.0');

        $settings = new PosSettings();
        $markets = array_values(array_filter($settings->get_markets(), static function (array $market): bool {
            return !empty($market['active']);
        }));

        wp_localize_script('bressol-pos-admin', 'bressolPos', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'findCustomerNonce' => wp_create_nonce('bressol_pos_find_customer'),
            'searchProductsNonce' => wp_create_nonce('bressol_pos_search_products'),
            'createOrderNonce' => wp_create_nonce('bressol_pos_create_order'),
            'pointsValueCents' => $settings->get_points_value_cents(),
            'minRedemptionPoints' => $settings->get_min_redemption_points(),
            'maxRedemptionPercent' => $settings->get_max_redemption_percent_of_order(),
            'markets' => array_map(static function (array $market): array {
                return [
                    'id' => (string) ($market['id'] ?? ''),
                    'name' => (string) ($market['name'] ?? ''),
                    'default_cost_cents' => (int) ($market['default_cost_cents'] ?? 0),
                ];
            }, $markets),
        ]);
    }

    public function handleFindCustomerAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_find_customer', 'nonce');
        $this->rate_limit_or_fail('find_customer', 15, 20);

        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;

        $auditLogger = class_exists(AuditLogger::class) ? new AuditLogger() : null;
        $customerService = new CustomerService($auditLogger);
        $lookupService = new CustomerLookupService($customerService, $auditLogger);

        $payload = null;
        if ($customerId > 0) {
            $payload = $lookupService->find_by_customer_id($customerId);
        } elseif ($token !== '') {
            $payload = $lookupService->find_by_public_id($token);
            if ($payload === null && ctype_digit($token)) {
                $payload = $lookupService->find_by_customer_id((int) $token);
            }
        }

        if ($payload === null) {
            $this->send_pos_error('pos_customer_not_found', 'Cliente no encontrado.', 404);
        }

        wp_send_json_success($payload);
    }

    public function handleSearchProductsAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_search_products', 'nonce');
        $this->rate_limit_or_fail('search_products', 20, 20);

        if (!function_exists('wc_get_products')) {
            $this->send_pos_error('pos_wc_missing', 'WooCommerce no disponible.', 400);
        }

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        if ($query === '') {
            $this->send_pos_error('pos_empty_query', 'Consulta vacía.', 422);
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit' => 10,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => $query,
        ]);

        $results = [];
        foreach ($products as $product) {
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $price = (float) $product->get_price();
            $results[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price_cents' => (int) round($price * 100),
            ];
        }

        wp_send_json_success($results);
    }

    public function handleCreateOrderAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_create_order', 'nonce');
        $this->rate_limit_or_fail('create_order', 8, 20);

        if (!function_exists('wc_create_order')) {
            $this->send_pos_error('pos_wc_missing', 'WooCommerce no disponible.', 400);
        }

        $settings = new PosSettings();
        $auditLogger = class_exists(AuditLogger::class) ? new AuditLogger() : null;
        $marketId = isset($_POST['market_id']) ? sanitize_text_field(wp_unslash($_POST['market_id'])) : '';
        $marketCostCents = isset($_POST['market_cost_cents']) ? (int) wp_unslash($_POST['market_cost_cents']) : 0;
        $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        $itemsRaw = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
        $items = json_decode((string) $itemsRaw, true);
        $pointsRedeem = isset($_POST['points_redeem']) ? max(0, (int) wp_unslash($_POST['points_redeem'])) : 0;
        $loyaltyOptIn = isset($_POST['loyalty_opt_in']) && wp_unslash($_POST['loyalty_opt_in']) === 'yes' ? 'yes' : 'no';
        $marketingOptIn = isset($_POST['marketing_opt_in']) && wp_unslash($_POST['marketing_opt_in']) === 'yes' ? 'yes' : 'no';

        if ($marketId === '') {
            $this->send_pos_error('pos_invalid_market', 'Mercado obligatorio.', 422);
        }

        $activeMarket = null;
        foreach ($settings->get_markets() as $market) {
            if (!empty($market['active']) && (string) ($market['id'] ?? '') === $marketId) {
                $activeMarket = $market;
                break;
            }
        }
        if ($activeMarket === null) {
            $this->send_pos_error('pos_invalid_market', 'Mercado inválido o inactivo.', 422);
        }

        if (!is_array($items) || $items === []) {
            $this->send_pos_error('pos_invalid_items', 'Carrito vacío.', 422);
        }

        $validatedItems = [];
        $itemsTotalCents = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
            if ($productId <= 0 || $qty < 1) {
                continue;
            }
            $product = wc_get_product($productId);
            if (!$product) {
                continue;
            }
            $priceCents = (int) round(((float) $product->get_price()) * 100);
            $itemsTotalCents += $priceCents * $qty;
            $validatedItems[] = [
                'product' => $product,
                'qty' => $qty,
            ];
        }

        if ($validatedItems === []) {
            $this->send_pos_error('pos_invalid_items', 'Productos inválidos.', 422);
        }

        $customerService = new CustomerService($auditLogger);
        $crmCustomerId = null;
        if ($customerId > 0) {
            $customer = $customerService->get_customer($customerId);
            if ($customer && (string) $customer->status === 'active') {
                $crmCustomerId = $customerId;
            }
        }

        $redemptionValueCents = 0;
        if ($pointsRedeem > 0) {
            if ($crmCustomerId === null) {
                $this->send_pos_error('pos_customer_inactive', 'Cliente no apto para canje.', 422);
            }
            if (!$customerService->is_loyalty_enabled($crmCustomerId)) {
                $this->send_pos_error('pos_loyalty_required', 'Cliente sin loyalty activo.', 422);
            }

            $minPoints = $settings->get_min_redemption_points();
            if ($pointsRedeem < $minPoints) {
                $this->send_pos_error('pos_points_below_min', 'Puntos insuficientes para canje mínimo.', 422);
            }

            $pointsValueCents = $settings->get_points_value_cents();
            $redemptionValueCents = $pointsRedeem * $pointsValueCents;
            if ($redemptionValueCents <= 0) {
                $this->send_pos_error('pos_invalid_redemption', 'Canje inválido.', 422);
            }

            $orderBaseCents = $itemsTotalCents + max(0, $marketCostCents);
            if ($orderBaseCents <= 0) {
                $this->send_pos_error('pos_invalid_total', 'Total inválido para canje.', 422);
            }

            $maxPercent = $settings->get_max_redemption_percent_of_order();
            $maxValueCents = (int) floor(($orderBaseCents * $maxPercent) / 100);
            if ($redemptionValueCents > $maxValueCents) {
                $this->send_pos_error('pos_redemption_exceeds_max', 'Canje excede el máximo permitido.', 422);
            }

            if ($redemptionValueCents >= $orderBaseCents) {
                $this->send_pos_error('pos_redemption_exceeds_total', 'El canje no puede dejar el total en negativo.', 422);
            }

            $pointsService = new PointsService(new CrmSettings(), $auditLogger ?? new AuditLogger());
            $balance = $pointsService->get_balance($crmCustomerId);
            if ($pointsRedeem > $balance) {
                $this->send_pos_error('pos_insufficient_points', 'Saldo de puntos insuficiente.', 422);
            }
        }

        $order = wc_create_order();
        foreach ($validatedItems as $item) {
            /** @var \WC_Product $product */
            $product = $item['product'];
            $order->add_product($product, $item['qty']);
        }

        $redemptionCouponCode = null;
        if ($redemptionValueCents > 0) {
            $coupon = $this->create_pos_coupon((int) $order->get_id(), $redemptionValueCents);
            if (!$coupon) {
                $this->send_pos_error('pos_coupon_failed', 'No se pudo preparar el canje.', 500);
            }
            $applyResult = $order->apply_coupon($coupon);
            if ($applyResult instanceof \WP_Error) {
                $this->send_pos_error('pos_coupon_apply_failed', 'No se pudo aplicar el canje.', 422);
            }
            $redemptionCouponCode = $coupon->get_code();
        }

        $order->update_meta_data('_bressol_pos_channel', 'pos');
        $order->update_meta_data('_bressol_pos_market_id', $marketId);
        $order->update_meta_data('_bressol_pos_market_name', (string) ($activeMarket['name'] ?? ''));
        $order->update_meta_data('_bressol_pos_market_cost_cents', max(0, $marketCostCents));
        $order->update_meta_data('_bressol_pos_operator_id', get_current_user_id());
        if ($crmCustomerId !== null) {
            $order->update_meta_data('_bressol_pos_customer_id', $crmCustomerId);
        }
        $order->update_meta_data('_bressol_loyalty_opt_in', $loyaltyOptIn);
        $order->update_meta_data('_bressol_marketing_opt_in', $marketingOptIn);
        if ($pointsRedeem > 0) {
            $order->update_meta_data('_bressol_pos_points_redeemed', $pointsRedeem);
            $order->update_meta_data('_bressol_pos_redemption_value_cents', $redemptionValueCents);
            $order->update_meta_data('_bressol_pos_redemption_coupon_code', $redemptionCouponCode);
            $order->update_meta_data('_bressol_pos_points_value_cents_snapshot', $settings->get_points_value_cents());
            $order->update_meta_data('_bressol_pos_min_redemption_points_snapshot', $settings->get_min_redemption_points());
            $order->update_meta_data('_bressol_pos_max_redemption_percent_snapshot', $settings->get_max_redemption_percent_of_order());
            $order->update_meta_data('_bressol_pos_redemption_calculated_from_total_cents', $orderBaseCents);
        }

        $order->calculate_totals();
        $order->set_status('completed');
        $order->save();

        if ($auditLogger instanceof AuditLogger) {
            $auditLogger->log('pos_order_created', 'order', (int) $order->get_id(), get_current_user_id(), [
                'market_id' => $marketId,
                'customer_id' => $crmCustomerId,
                'total' => $order->get_total(),
                'items_count' => count($validatedItems),
            ]);
        }

        if ($pointsRedeem > 0 && $crmCustomerId !== null) {
            $pointsService = new PointsService(new CrmSettings(), $auditLogger ?? new AuditLogger());
            $reference = 'pos-order-' . $order->get_id();
            $order->update_meta_data('_bressol_pos_redemption_reference', $reference);
            $redemptionId = $pointsService->create_redemption($crmCustomerId, $pointsRedeem, 'pos', $reference, null);
            if ($redemptionId > 0 && $auditLogger instanceof AuditLogger) {
                $auditLogger->log('pos_points_redeemed', 'order', (int) $order->get_id(), get_current_user_id(), [
                    'market_id' => $marketId,
                    'customer_id' => $crmCustomerId,
                    'points' => $pointsRedeem,
                    'value_cents' => $redemptionValueCents,
                ]);
            }
        }

        wp_send_json_success([
            'order_id' => $order->get_id(),
        ]);
    }

    private function get_capability(): string
    {
        return class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
    }

    private function rate_limit_or_fail(string $action, int $limit, int $windowSeconds): void
    {
        $userId = get_current_user_id();
        $key = 'bressol_pos_rl_' . $action . '_' . $userId;
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            $this->send_pos_error('pos_rate_limited', 'Demasiadas solicitudes. Intenta de nuevo en unos segundos.', 429);
        }

        $count++;
        set_transient($key, $count, $windowSeconds);
    }

    /** @param array<string, mixed>|null $details */
    private function send_pos_error(string $code, string $message, int $status, ?array $details = null): void
    {
        $payload = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $payload['details'] = $details;
        }
        wp_send_json_error($payload, $status);
    }

    private function create_pos_coupon(int $orderId, int $valueCents): ?\WC_Coupon
    {
        if ($orderId <= 0 || $valueCents <= 0) {
            return null;
        }

        $code = 'pos-redeem-' . $orderId . '-' . wp_generate_password(6, false, false);
        $coupon = new \WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount(number_format($valueCents / 100, 2, '.', ''));
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_usage_limit_per_user(1);
        if (method_exists($coupon, 'set_apply_before_tax')) {
            $coupon->set_apply_before_tax(true);
        } else {
            $coupon->add_meta_data('apply_before_tax', 'yes', true);
        }
        $coupon->add_meta_data('_bressol_pos_coupon', '1', true);
        $coupon->add_meta_data('_bressol_pos_order_id', (string) $orderId, true);
        $coupon->add_meta_data('_bressol_pos_created_at', (string) time(), true);

        $couponId = $coupon->save();
        if (!$couponId) {
            return null;
        }

        return $coupon;
    }

    public static function schedule_cron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'daily', self::CRON_HOOK);
        }
    }

    public static function clear_cron(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private function get_coupon_retention_days(): int
    {
        $days = self::DEFAULT_COUPON_RETENTION_DAYS;
        if (defined('BRESSOL_POS_COUPON_RETENTION_DAYS')) {
            $days = (int) BRESSOL_POS_COUPON_RETENTION_DAYS;
        } else {
            $days = (int) get_option('bressol_pos_coupon_retention_days', $days);
        }

        return max(1, $days);
    }

    public function cleanup_pos_coupons(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        if (!class_exists('WC_Coupon') || !function_exists('wc_get_order')) {
            return;
        }

        $cutoffTimestamp = time() - ($this->get_coupon_retention_days() * DAY_IN_SECONDS);
        $auditLogger = class_exists(AuditLogger::class) ? new AuditLogger() : null;

        $paged = 1;
        do {
            $query = new \WP_Query([
                'post_type' => 'shop_coupon',
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'paged' => $paged,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_bressol_pos_coupon',
                        'value' => '1',
                    ],
                    [
                        'key' => '_bressol_pos_order_id',
                        'compare' => 'EXISTS',
                    ],
                    [
                        'key' => '_bressol_pos_created_at',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]);

            if (!$query->have_posts()) {
                break;
            }

            foreach ($query->posts as $couponId) {
                $couponId = (int) $couponId;
                $coupon = new \WC_Coupon($couponId);
                $code = (string) $coupon->get_code();
                if ($code === '' || strpos($code, 'pos-redeem-') !== 0) {
                    continue;
                }

                if ((int) $coupon->get_usage_count() < 1) {
                    continue;
                }

                $createdAt = (int) get_post_meta($couponId, '_bressol_pos_created_at', true);
                if ($createdAt <= 0 || $createdAt > $cutoffTimestamp) {
                    continue;
                }

                $orderId = (int) get_post_meta($couponId, '_bressol_pos_order_id', true);
                if ($orderId <= 0) {
                    continue;
                }

                $order = wc_get_order($orderId);
                if (!$order || $order->get_status() !== 'completed') {
                    continue;
                }

                wp_delete_post($couponId, true);

                if ($auditLogger instanceof AuditLogger) {
                    $deletedAt = current_time('mysql');
                    $auditLogger->log('pos_coupon_deleted', 'coupon', $couponId, null, [
                        'order_id' => $orderId,
                        'created_at' => $createdAt,
                        'deleted_at' => $deletedAt,
                    ]);
                }
            }

            $paged++;
        } while ($paged <= $query->max_num_pages);
    }
}
