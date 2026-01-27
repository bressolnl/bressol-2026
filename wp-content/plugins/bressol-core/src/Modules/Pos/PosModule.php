<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Crm\Services\AuditLogger;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Crm\Services\PointsService;
use Bressol\Modules\Crm\Services\Settings as CrmSettings;
use Bressol\Modules\CostMargin\Services\MarginRulesService;
use Bressol\Modules\CostMargin\Services\MarginAuditService;
use Bressol\Modules\MarketsEvents\Services\MarketsEventsPosMarketProvider;
use Bressol\Modules\MarketsEvents\Services\PosEventContextService;
use Bressol\Modules\Inventory\Services\AuditLogger as InventoryAuditLogger;
use Bressol\Modules\Inventory\Services\CacheService as InventoryCacheService;
use Bressol\Modules\Inventory\Services\SellableService;
use Bressol\Modules\Pos\Installer;
use Bressol\Modules\Pos\Admin\AdminPages;
use Bressol\Modules\Pos\Services\CustomerLookupService;
use Bressol\Modules\Pos\Services\InternalOrderService;
use Bressol\Modules\Pos\Services\InternalOrderStatus;
use Bressol\Modules\Pos\Services\OpenedItemsService;
use Bressol\Modules\Pos\Services\PosMarketsCatalog;
use Bressol\Modules\Pos\Services\PosMarketResolver;
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
        (new InternalOrderStatus())->register();
        add_action('admin_init', [Installer::class, 'maybe_upgrade']);

        if (defined('WP_CLI') && WP_CLI) {
            Installer::maybe_upgrade();
        }

        if (!is_admin()) {
            return;
        }

        $settings = new PosSettings();
        $auditLogger = class_exists(AuditLogger::class) ? new AuditLogger() : null;
        $customerService = class_exists(CustomerService::class) ? new CustomerService($auditLogger) : null;
        $lookupService = null;
        if ($customerService !== null && class_exists(CustomerLookupService::class)) {
            $lookupService = new CustomerLookupService($customerService, $auditLogger);
        }
        $adminPages = new AdminPages($settings, $customerService, $lookupService);

        add_action('admin_menu', [$adminPages, 'registerMenus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_ajax_bressol_pos_find_customer', [$this, 'handleFindCustomerAjax']);
        add_action('wp_ajax_bressol_pos_search_products', [$this, 'handleSearchProductsAjax']);
        add_action('wp_ajax_bressol_pos_create_order', [$this, 'handleCreateOrderAjax']);
        add_action('wp_ajax_bressol_pos_open_sampling_item', [$this, 'handleOpenSamplingItemAjax']);
        add_action('wp_ajax_bressol_pos_list_opened_items', [$this, 'handleListOpenedItemsAjax']);
        add_action('wp_ajax_bressol_pos_discard_opened_items', [$this, 'handleDiscardOpenedItemsAjax']);
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
        $marketsProvider = new MarketsEventsPosMarketProvider();
        $marketsCatalog = new PosMarketsCatalog($marketsProvider, $settings);
        $markets = $marketsCatalog->list();
        $activeEventId = (new PosEventContextService())->get_active_event_id(get_current_user_id());

        wp_localize_script('bressol-pos-admin', 'bressolPos', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'findCustomerNonce' => wp_create_nonce('bressol_pos_find_customer'),
            'searchProductsNonce' => wp_create_nonce('bressol_pos_search_products'),
            'createOrderNonce' => wp_create_nonce('bressol_pos_create_order'),
            'openSamplingNonce' => wp_create_nonce('bressol_pos_open_sampling_item'),
            'openSamplingAction' => 'bressol_pos_open_sampling_item',
            'listOpenedItemsNonce' => wp_create_nonce('bressol_pos_list_opened_items'),
            'discardOpenedItemsNonce' => wp_create_nonce('bressol_pos_discard_opened_items'),
            'pointsValueCents' => $settings->get_points_value_cents(),
            'minRedemptionPoints' => $settings->get_min_redemption_points(),
            'maxRedemptionPercent' => $settings->get_max_redemption_percent_of_order(),
            'markets' => $markets,
            'activeEventId' => $activeEventId,
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

        if (!class_exists(CustomerService::class) || !class_exists(CustomerLookupService::class)) {
            $this->send_pos_error('pos_crm_unavailable', 'CRM no disponible.', 503);
        }

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
        $explicitEventId = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $customerId = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        $itemsRaw = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
        $items = json_decode((string) $itemsRaw, true);
        $pointsRedeem = isset($_POST['points_redeem']) ? max(0, (int) wp_unslash($_POST['points_redeem'])) : 0;
        $loyaltyOptIn = isset($_POST['loyalty_opt_in']) && wp_unslash($_POST['loyalty_opt_in']) === 'yes' ? 'yes' : 'no';
        $marketingOptIn = isset($_POST['marketing_opt_in']) && wp_unslash($_POST['marketing_opt_in']) === 'yes' ? 'yes' : 'no';

        if ($marketId === '' && $explicitEventId <= 0) {
            $this->send_pos_error('pos_invalid_market', 'Mercado obligatorio.', 422);
        }

        if ($explicitEventId > 0) {
            $marketId = 'event:' . $explicitEventId;
        }
        $resolver = new PosMarketResolver($settings);
        try {
            $resolvedMarket = $resolver->resolve($marketId);
        } catch (\Throwable $exception) {
            $this->send_pos_error('pos_invalid_market', 'Mercado inválido o inactivo.', 422);
        }

        $marketId = $resolvedMarket['market_id'];
        $marketName = $resolvedMarket['market_name'];
        $eventId = $resolvedMarket['event_id'];

        if (!is_array($items) || $items === []) {
            $this->send_pos_error('pos_invalid_items', 'Carrito vacío.', 422);
        }

        $sellableService = SellableService::build_default(new InventoryCacheService(), new InventoryAuditLogger());
        $validatedItems = [];
        $itemsTotalCents = 0;
        $usedPriceFallback = false;
        $rulesItems = [];
        // Expected POS items payload:
        // - product_id (int)
        // - qty (int)
        // - pack_selection (optional): { "<product_id>": <qty>, ... }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
            $packSelection = $item['pack_selection'] ?? null;
            if ($productId <= 0 || $qty < 1) {
                continue;
            }
            $product = wc_get_product($productId);
            if (!$product) {
                continue;
            }
            $selection = is_array($packSelection) ? $packSelection : null;
            if (!$sellableService->is_sellable($productId, $qty, $selection)) {
                $name = $product->get_name();
                $message = $name !== '' ? ('Stock insuficiente: ' . $name . '.') : 'Stock insuficiente.';
                $sellableService->log_blocked('pos_create_order', $productId, $qty, $selection);
                $this->send_pos_error('pos_stock_insufficient', $message, 422);
            }
            $priceCents = (int) round(((float) $product->get_price()) * 100);
            $itemsTotalCents += $priceCents * $qty;
            $validatedItems[] = [
                'product' => $product,
                'qty' => $qty,
            ];
            $priceExclCents = null;
            if (isset($item['price_excl_tax_cents']) && is_numeric($item['price_excl_tax_cents'])) {
                $priceExclCents = (int) $item['price_excl_tax_cents'];
            } elseif (function_exists('wc_get_price_excluding_tax')) {
                $priceExcl = wc_get_price_excluding_tax($product, ['qty' => 1]);
                $priceExclCents = (int) round(((float) $priceExcl) * 100);
                $usedPriceFallback = true;
            }
            $rulesItems[] = [
                'product_id' => $productId,
                'qty' => $qty,
                'price_excl_tax_cents' => $priceExclCents,
                'pack_selection' => $selection,
            ];
        }

        if ($validatedItems === []) {
            $this->send_pos_error('pos_invalid_items', 'Productos inválidos.', 422);
        }

        $isClearance = isset($_POST['is_clearance']) && wp_unslash($_POST['is_clearance']) === 'yes';
        $rulesService = new MarginRulesService();
        $rulesResult = $rulesService->evaluate_cart($rulesItems, [
            'channel' => 'pos',
            'market_cost_cents' => 0,
            'is_clearance' => $isClearance,
        ]);
        $marginPayload = [
            'margin_status' => $rulesResult['status'],
            'margin_violations' => $rulesResult['violations'],
            'margin_computed' => $rulesResult['computed'],
        ];
        if ($rulesResult['status'] === 'block') {
            $this->send_pos_error('pos_margin_blocked', 'Margen insuficiente.', 422, $marginPayload);
        }

        $customerService = class_exists(CustomerService::class) ? new CustomerService($auditLogger) : null;
        $crmRequired = $customerId > 0 || $pointsRedeem > 0 || $loyaltyOptIn === 'yes' || $marketingOptIn === 'yes';
        if ($crmRequired && $customerService === null) {
            $this->send_pos_error('pos_crm_unavailable', 'CRM no disponible.', 503);
        }

        $crmCustomerId = null;
        if ($customerId > 0) {
            $customer = $customerService->get_customer($customerId);
            if ($customer && (string) $customer->status === 'active') {
                $crmCustomerId = $customerId;
            }
        }

        $redemptionValueCents = 0;
        $pointsService = null;
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

            $orderBaseCents = $itemsTotalCents;
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

            if (!class_exists(PointsService::class) || !class_exists(CrmSettings::class) || !class_exists(AuditLogger::class)) {
                $this->send_pos_error('pos_crm_unavailable', 'CRM no disponible.', 503);
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
        $order->update_meta_data('_bressol_pos_market_name', $marketName);
        $order->update_meta_data('_bressol_pos_market_cost_cents', 0);
        $order->update_meta_data('_bressol_event_id', $eventId);
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

        $priceSource = $usedPriceFallback ? 'woo_fallback' : 'explicit';
        try {
            (new MarginAuditService())->persist_from_result($order, $rulesResult, [
                'channel' => 'pos',
                'market_cost_cents' => 0,
                'price_source' => $priceSource,
            ]);
        } catch (\Throwable $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[bressol_margin] persist_failed order=' . (int) $order->get_id());
            }
        }

        if ($auditLogger instanceof AuditLogger) {
            $auditLogger->log('pos_order_created', 'order', (int) $order->get_id(), get_current_user_id(), [
                'market_id' => $marketId,
                'customer_id' => $crmCustomerId,
                'total' => $order->get_total(),
                'items_count' => count($validatedItems),
            ]);
        }

        if ($pointsRedeem > 0 && $crmCustomerId !== null && $pointsService instanceof PointsService) {
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

        $response = [
            'order_id' => $order->get_id(),
        ] + $marginPayload;
        wp_send_json_success($response);
    }

    public function handleOpenSamplingItemAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_open_sampling_item', 'nonce');
        $this->rate_limit_or_fail('open_sampling_item', 20, 20);

        if (!function_exists('wc_create_order')) {
            $this->send_pos_error('pos_wc_missing', 'WooCommerce no disponible.', 400);
        }

        $marketId = isset($_POST['market_id']) ? sanitize_text_field(wp_unslash($_POST['market_id'])) : '';
        $eventId = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $productId = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $qty = isset($_POST['qty']) ? absint($_POST['qty']) : 0;

        $resolver = new PosMarketResolver(new PosSettings());
        try {
            if ($marketId !== '') {
                $resolved = $resolver->resolve($marketId);
                $eventId = (int) $resolved['event_id'];
            } elseif ($eventId > 0) {
                $resolved = $resolver->resolve('event:' . $eventId);
                $eventId = (int) $resolved['event_id'];
            } else {
                $this->send_pos_error('pos_invalid_event', 'Evento inválido.', 422);
            }
        } catch (\Throwable $exception) {
            $this->send_pos_error('pos_invalid_event', 'Evento inválido.', 422);
        }

        $service = new InternalOrderService();
        try {
            $orderId = $service->create_sampling_open_order($eventId, get_current_user_id(), [[
                'product_id' => $productId,
                'qty' => $qty,
            ]]);
        } catch (\Throwable $exception) {
            $this->send_pos_error('pos_internal_order_failed', $exception->getMessage(), 422);
        }

        $openedItemsService = new OpenedItemsService();
        try {
            $openedItemId = $openedItemsService->create_opened_item(
                $eventId,
                $productId,
                get_current_user_id(),
                $qty,
                $orderId
            );
        } catch (\Throwable $exception) {
            // Compensation: mark the internal order as invalid and avoid stock reduction.
            $service->mark_internal_invalid($orderId);
            $this->send_pos_error('pos_opened_item_failed', $exception->getMessage(), 422);
        }

        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            $service->mark_internal_invalid($orderId);
            $this->send_pos_error('pos_opened_item_failed', 'Pedido interno no disponible.', 422);
        }

        $matched = false;
        foreach ($order->get_items() as $item) {
            if ((int) $item->get_product_id() === $productId) {
                $item->update_meta_data('_bressol_opened_item_id', $openedItemId);
                $item->save();
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            // Compensation: keep the order but mark it invalid for manual review.
            $service->mark_internal_invalid($orderId);
            $this->send_pos_error('pos_opened_item_failed', 'No se pudo vincular el item abierto.', 422);
        }

        try {
            $service->reduce_stock_once($orderId);
        } catch (\Throwable $exception) {
            $service->mark_internal_invalid($orderId);
            $this->send_pos_error('pos_stock_failed', $exception->getMessage(), 422);
        }

        wp_send_json_success([
            'order_id' => $orderId,
            'opened_item_id' => $openedItemId,
            'stock_reduced' => true,
        ]);
    }

    public function handleListOpenedItemsAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_list_opened_items', 'nonce');
        $this->rate_limit_or_fail('list_opened_items', 30, 20);

        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

        $service = new OpenedItemsService();
        $items = $service->list_open_items($limit, $page);

        $payload = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $productName = '';
            if ($productId > 0 && function_exists('wc_get_product')) {
                $product = wc_get_product($productId);
                if ($product instanceof \WC_Product) {
                    $productName = $product->get_name();
                }
            }

            $payload[] = [
                'id' => (int) ($item['id'] ?? 0),
                'product_id' => $productId,
                'product_name' => $productName,
                'opened_at' => (string) ($item['opened_at'] ?? ''),
                'opened_event_id' => (int) ($item['opened_event_id'] ?? 0),
                'opened_by_user_id' => (int) ($item['opened_by_user_id'] ?? 0),
                'initial_qty' => (int) ($item['initial_qty'] ?? 0),
                'internal_order_id' => (int) ($item['internal_order_id'] ?? 0),
                'status' => (string) ($item['status'] ?? ''),
            ];
        }

        wp_send_json_success([
            'items' => $payload,
        ]);
    }

    public function handleDiscardOpenedItemsAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_discard_opened_items', 'nonce');
        $this->rate_limit_or_fail('discard_opened_items', 20, 20);

        $idsRaw = isset($_POST['opened_item_ids']) ? wp_unslash($_POST['opened_item_ids']) : null;
        $singleId = isset($_POST['opened_item_id']) ? absint($_POST['opened_item_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

        $ids = [];
        if (is_string($idsRaw) && $idsRaw !== '') {
            $decoded = json_decode($idsRaw, true);
            if (is_array($decoded)) {
                $ids = array_map('intval', $decoded);
            }
        }
        if ($ids === [] && $singleId > 0) {
            $ids = [$singleId];
        }

        $service = new OpenedItemsService();
        try {
            $updated = $service->discard_opened_items($ids, $reason);
        } catch (\Throwable $exception) {
            $this->send_pos_error('pos_discard_failed', $exception->getMessage(), 422);
        }

        wp_send_json_success([
            'discarded' => $updated,
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
