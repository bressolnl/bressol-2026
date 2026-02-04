<?php
declare(strict_types=1);

namespace Bressol\Modules\Pos;

use Bressol\Core\ModuleInterface;
use Bressol\Modules\Crm\Services\AuditLogger;
use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Crm\Services\CustomerSearchService;
use Bressol\Modules\Crm\Services\PointsService;
use Bressol\Modules\Crm\Services\Settings as CrmSettings;
use Bressol\Modules\Esp\Services\Settings as EspSettings;
use Bressol\Modules\CostMargin\Services\MarginRulesService;
use Bressol\Modules\CostMargin\Services\MarginAuditService;
use Bressol\Modules\CostMargin\Services\CostMarginService;
use Bressol\Modules\MarketsEvents\Services\MarketsEventsPosMarketProvider;
use Bressol\Modules\MarketsEvents\Services\PosEventContextService;
use Bressol\Modules\MarketsEvents\Repositories\EventRepository;
use Bressol\Modules\MarketsEvents\Services\PosEventEligibilityService;
use Bressol\Modules\MarketsEvents\Services\EventCostService;
use Bressol\Modules\Forecasting\Services\PosSalesSnapshotBuilder;
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
use Bressol\Modules\Pos\Services\BundlePicksService;

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
        add_action('wp_ajax_bressol_pos_create_customer', [$this, 'handleCreateCustomerAjax']);
        add_action('wp_ajax_bressol_pos_search_products', [$this, 'handleSearchProductsAjax']);
        add_action('wp_ajax_bressol_pos_create_order', [$this, 'handleCreateOrderAjax']);
        add_action('wp_ajax_bressol_pos_open_sampling_item', [$this, 'handleOpenSamplingItemAjax']);
        add_action('wp_ajax_bressol_pos_list_opened_items', [$this, 'handleListOpenedItemsAjax']);
        add_action('wp_ajax_bressol_pos_discard_opened_items', [$this, 'handleDiscardOpenedItemsAjax']);
        add_action('wp_ajax_bressol_pos_bootstrap', [$this, 'handleBootstrapAjax']);
        add_action('wp_ajax_bressol_pos_set_active_event', [$this, 'handleSetActiveEventAjax']);
        add_action('wp_ajax_bressol_pos_mark_sampling_control_done', [$this, 'handleMarkSamplingControlDoneAjax']);
        add_action('wp_ajax_bressol_pos_complete_sampling_control', [$this, 'handleCompleteSamplingControlAjax']);
        add_action('wp_ajax_bressol_pos_dashboard', [$this, 'handleDashboardAjax']);
        add_action('wp_ajax_bressol_pos_close_register', [$this, 'handleCloseRegisterAjax']);
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
        $activeEvent = $this->resolve_active_event_context();

        wp_localize_script('bressol-pos-admin', 'bressolPos', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'findCustomerNonce' => wp_create_nonce('bressol_pos_find_customer'),
            'createCustomerNonce' => wp_create_nonce('bressol_pos_create_customer'),
            'searchProductsNonce' => wp_create_nonce('bressol_pos_search_products'),
            'createOrderNonce' => wp_create_nonce('bressol_pos_create_order'),
            'openSamplingNonce' => wp_create_nonce('bressol_pos_open_sampling_item'),
            'openSamplingAction' => 'bressol_pos_open_sampling_item',
            'listOpenedItemsNonce' => wp_create_nonce('bressol_pos_list_opened_items'),
            'discardOpenedItemsNonce' => wp_create_nonce('bressol_pos_discard_opened_items'),
            'bootstrapNonce' => wp_create_nonce('bressol_pos_bootstrap'),
            'setActiveEventNonce' => wp_create_nonce('bressol_pos_set_active_event'),
            'markSamplingControlNonce' => wp_create_nonce('bressol_pos_mark_sampling_control_done'),
            'dashboardNonce' => wp_create_nonce('bressol_pos_dashboard'),
            'closeRegisterNonce' => wp_create_nonce('bressol_pos_close_register'),
            'pointsValueCents' => $settings->get_points_value_cents(),
            'minRedemptionPoints' => $settings->get_min_redemption_points(),
            'maxRedemptionPercent' => $settings->get_max_redemption_percent_of_order(),
            'markets' => $markets,
            'activeEventId' => $activeEvent['id'] ?? 0,
            'activeEvent' => $activeEvent,
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

        if ($payload === null && class_exists(CustomerSearchService::class) && $token !== '') {
            $search = new CustomerSearchService();
            $results = $search->search($token, 1, 10);
            $items = isset($results['items']) && is_array($results['items']) ? $results['items'] : [];
            $total = isset($results['total']) ? (int) $results['total'] : 0;
            if ($total === 1 && isset($items[0]['id'])) {
                $payload = $lookupService->find_by_customer_id((int) $items[0]['id']);
            } elseif ($total > 1) {
                $matches = [];
                foreach ($items as $item) {
                    $first = isset($item['first_name']) ? (string) $item['first_name'] : '';
                    $last = isset($item['last_name']) ? (string) $item['last_name'] : '';
                    $name = trim($first . ' ' . $last);
                    if ($name === '') {
                        $name = 'Cliente #' . (int) ($item['id'] ?? 0);
                    }
                    $matches[] = [
                        'id' => (int) ($item['id'] ?? 0),
                        'display_name' => $name,
                    ];
                }
                $this->send_pos_error('pos_customer_multiple', 'Varios clientes encontrados.', 409, [
                    'matches' => $matches,
                ]);
            }
        }

        if ($payload === null) {
            $this->send_pos_error('pos_customer_not_found', 'Cliente no encontrado.', 404);
        }

        $activeEvent = $this->resolve_active_event_context();
        $customer = $customerService->get_customer((int) ($payload['customer_id'] ?? 0));
        if ($customer) {
            $payload['email'] = (string) ($customer->email ?? '');
            $payload['first_name'] = (string) ($customer->first_name ?? '');
            $payload['city'] = (string) ($customer->city ?? '');
            $payload['status'] = (string) ($customer->status ?? 'active');
            $payload['loyalty_enabled'] = (bool) ($customer->loyalty_enabled ?? false);
        }
        if (!isset($payload['display_name'])) {
            $name = trim((string) ($payload['first_name'] ?? ''));
            $payload['display_name'] = $name !== '' ? $name : (($payload['email'] ?? '') !== '' ? $payload['email'] : 'Cliente');
        }
        $payload['bonus_points'] = 0;
        $payload['event'] = $activeEvent
            ?? ($customer && (int) ($customer->source_event_id ?? 0) > 0
                ? $this->get_event_context_by_id((int) $customer->source_event_id)
                : null);

        wp_send_json_success($payload);
    }

    public function handleCreateCustomerAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_create_customer', 'nonce');
        $this->rate_limit_or_fail('create_customer', 10, 20);

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $firstName = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';
        $loyaltyEnabled = isset($_POST['loyalty_enabled']) && wp_unslash($_POST['loyalty_enabled']) === '1' ? 1 : 0;
        $canReceiveMarketing = isset($_POST['can_receive_marketing']) && wp_unslash($_POST['can_receive_marketing']) === '1' ? 1 : 0;
        $activeEvent = $this->resolve_active_event_context();
        $eventId = $activeEvent['id'] ?? 0;

        if ($email === '' || !is_email($email)) {
            $this->send_pos_error('pos_invalid_email', 'Email inválido.', 422);
        }

        global $wpdb;
        $customersTable = $wpdb->prefix . 'bressol_crm_customers';
        $ledgerTable = $wpdb->prefix . 'bressol_crm_points_ledger';

        $customersExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $customersTable)) === $customersTable;
        $ledgerExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ledgerTable)) === $ledgerTable;
        if (!$customersExists) {
            $this->send_pos_error('pos_crm_missing_tables', 'CRM no instalado o incompleto.', 503);
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, first_name, city FROM {$customersTable} WHERE email = %s AND customer_type = %s LIMIT 1",
                $email,
                'b2c'
            )
        );

        $now = current_time('mysql');
        $customerId = 0;
        $wasCreated = false;
        if ($existing) {
            $customerId = (int) $existing->id;
            $update = [
                'loyalty_enabled' => $loyaltyEnabled,
                'can_receive_marketing' => $canReceiveMarketing,
                'can_be_profiled' => $canReceiveMarketing,
                'updated_at' => $now,
            ];
            $formats = ['%d', '%d', '%d', '%s'];
            if ($firstName !== '') {
                $update['first_name'] = $firstName;
                $formats[] = '%s';
            }
            if ($city !== '') {
                $update['city'] = $city;
                $formats[] = '%s';
            }
            if ($eventId > 0) {
                $update['source_event_id'] = $eventId;
                $formats[] = '%d';
            }
            $wpdb->update($customersTable, $update, ['id' => $customerId], $formats, ['%d']);
        } else {
            $insert = [
                'email' => $email,
                'first_name' => $firstName !== '' ? $firstName : null,
                'city' => $city !== '' ? $city : null,
                'customer_type' => 'b2c',
                'status' => 'active',
                'loyalty_enabled' => $loyaltyEnabled,
                'can_receive_marketing' => $canReceiveMarketing,
                'can_be_profiled' => $canReceiveMarketing,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $formats = ['%s','%s','%s','%s','%s','%d','%d','%d','%s','%s'];
            if ($eventId > 0) {
                $insert['source_event_id'] = $eventId;
                $formats[] = '%d';
            }
            $inserted = $wpdb->insert(
                $customersTable,
                $insert,
                $formats
            );
            if (!$inserted) {
                $this->send_pos_error('pos_customer_create_failed', 'No se pudo crear el cliente.', 500);
            }
            $customerId = (int) $wpdb->insert_id;
            $wasCreated = true;
        }

        $auditLogger = class_exists(AuditLogger::class) ? new AuditLogger() : null;
        $bonusPoints = 0;
        if ($loyaltyEnabled === 1 && $ledgerExists && class_exists(PointsService::class)) {
            $pointsService = new PointsService(new CrmSettings(), $auditLogger ?? new AuditLogger());
            $bonusPoints = $pointsService->award_signup_bonus($customerId, 300, $eventId > 0 ? $eventId : null);
        } elseif ($loyaltyEnabled === 1 && !$ledgerExists && $auditLogger instanceof AuditLogger) {
            $auditLogger->log('signup_bonus_skipped_missing_ledger', 'customer', $customerId, null, [
                'email' => $email,
            ]);
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, email, first_name, last_name, status, loyalty_enabled, can_receive_marketing, city, source_event_id
                 FROM {$customersTable} WHERE id = %d LIMIT 1",
                $customerId
            )
        );

        $displayName = $firstName;
        $firstName = $row ? (string) ($row->first_name ?? $firstName) : $firstName;
        $city = $row ? (string) ($row->city ?? $city) : $city;
        $lastName = $row ? (string) ($row->last_name ?? '') : '';
        $email = $row ? (string) ($row->email ?? $email) : $email;
        $status = $row ? (string) ($row->status ?? 'active') : 'active';
        $loyaltyEnabled = $row ? ((int) ($row->loyalty_enabled ?? 0)) : $loyaltyEnabled;
        if ($displayName === '') {
            $displayName = trim($firstName . ' ' . $lastName);
        }
        if ($displayName === '') {
            $displayName = $email !== '' ? $email : ('Cliente #' . $customerId);
        }

        $marketingEffective = false;
        if (class_exists(CustomerService::class)) {
            $customerService = new CustomerService($auditLogger);
            $marketingState = $customerService->get_effective_marketing_state($email);
            $marketingEffective = (bool) ($marketingState['effective_flags']['can_receive_marketing'] ?? false);
        } else {
            $marketingEffective = $status === 'active' && $canReceiveMarketing === 1;
        }

        $payload = [
            'customer_id' => $customerId,
            'email' => $email,
            'display_name' => $displayName,
            'loyalty_enabled' => $loyaltyEnabled === 1,
            'marketing_effective' => $marketingEffective,
            'status' => $status,
            'first_name' => $firstName,
            'city' => $city,
            'bonus_points' => $bonusPoints,
        ];

        if (class_exists(CustomerLookupService::class) && class_exists(CustomerService::class)) {
            $lookupService = new CustomerLookupService(new CustomerService($auditLogger), $auditLogger);
            $lookupPayload = $lookupService->find_by_customer_id($customerId);
            if (is_array($lookupPayload) && isset($lookupPayload['masked_public_id'])) {
                $payload['masked_public_id'] = (string) $lookupPayload['masked_public_id'];
            }
        } elseif (class_exists(\Bressol\Modules\Crm\Services\CustomerSearchService::class)) {
            $payload['masked_public_id'] = (string) $customerId;
        }

        $payload['event'] = $activeEvent
            ?? ($row && (int) ($row->source_event_id ?? 0) > 0
                ? $this->get_event_context_by_id((int) $row->source_event_id)
                : null);

        // POS transactional email — NEVER use ESP
        $context = 'pos';
        $emailResult = ['sent' => false, 'error' => ''];
        if ($context === 'pos' && $loyaltyEnabled === 1) {
            $emailResult = $this->send_pos_welcome_email($email, $firstName, $city, $payload['event']);
        }
        $payload['email_sent'] = (bool) ($emailResult['sent'] ?? false);
        if (!$payload['email_sent'] && defined('WP_DEBUG') && WP_DEBUG) {
            $payload['email_error'] = (string) ($emailResult['error'] ?? 'wp_mail_failed');
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
        $categoryRaw = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
        $excludeBundles = isset($_POST['exclude_bundles']) && wp_unslash($_POST['exclude_bundles']) === '1';
        if ($query === '') {
            $this->send_pos_error('pos_empty_query', 'Consulta vacía.', 422);
        }

        $args = [
            'status' => 'publish',
            'limit' => 10,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => $query,
        ];
        $categorySlugs = $this->parse_bundle_categories($categoryRaw);
        if ($categorySlugs !== []) {
            $args['category'] = $categorySlugs;
        }

        $products = wc_get_products($args);

        $results = [];
        foreach ($products as $product) {
            if (!$product instanceof \WC_Product) {
                continue;
            }
        $bundleMeta = $this->get_bundle_meta($product->get_id());
            if ($excludeBundles && $bundleMeta['is_bundle']) {
                continue;
            }
            $price = (float) $product->get_price();
            $results[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price_cents' => (int) round($price * 100),
                'is_bundle' => $bundleMeta['is_bundle'],
                'bundle_qty' => $bundleMeta['bundle_qty'],
                'bundle_pick_category' => $bundleMeta['bundle_pick_category'],
                'bundle_pick_rules' => $bundleMeta['bundle_pick_rules'],
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
        $this->require_pos_session_for_today(get_current_user_id(), true);

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
        $customerEmail = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $customerFirstName = isset($_POST['customer_first_name']) ? sanitize_text_field(wp_unslash($_POST['customer_first_name'])) : '';
        $customerLastName = isset($_POST['customer_last_name']) ? sanitize_text_field(wp_unslash($_POST['customer_last_name'])) : '';
        $paymentMethod = isset($_POST['payment_method']) ? sanitize_key(wp_unslash($_POST['payment_method'])) : 'cash';
        $paymentReference = isset($_POST['payment_reference']) ? sanitize_text_field(wp_unslash($_POST['payment_reference'])) : '';

        if ($marketId === '' && $explicitEventId <= 0) {
            $this->send_pos_error('pos_invalid_market', 'Mercado obligatorio.', 422);
        }

        if (!in_array($paymentMethod, ['cash', 'pin', 'tikkie'], true)) {
            $this->send_pos_error('pos_invalid_payment', 'Método de pago inválido.', 422);
        }

        if ($explicitEventId > 0) {
            $marketId = 'event:' . $explicitEventId;
        }
        $resolver = new PosMarketResolver($settings);
        try {
            $resolvedMarket = $resolver->resolve($marketId);
        } catch (\Throwable $exception) {
            $message = $exception->getMessage() === 'event not eligible'
                ? 'No hay eventos elegibles hoy.'
                : 'Mercado inválido o inactivo.';
            $code = $exception->getMessage() === 'event not eligible'
                ? 'pos_event_not_eligible'
                : 'pos_invalid_market';
            $this->send_pos_error($code, $message, 422);
        }

        $marketId = $resolvedMarket['market_id'];
        $marketName = $resolvedMarket['market_name'];
        $eventId = $resolvedMarket['event_id'];

        if ($eventId > 0) {
            $eligibility = new PosEventEligibilityService(new EventRepository());
            $event = (new EventRepository())->find_by_id($eventId);
            if (!$event || !$eligibility->is_event_eligible_for_pos($event)) {
                $this->send_pos_error('pos_event_not_eligible', 'No hay eventos elegibles hoy.', 422);
            }
        }

        if (!is_array($items) || $items === []) {
            $this->send_pos_error('pos_invalid_items', 'Carrito vacío.', 422);
        }

        $sellableService = SellableService::build_default(new InventoryCacheService(), new InventoryAuditLogger());
        $validatedItems = [];
        $itemsTotalCents = 0;
        $usedPriceFallback = false;
        $rulesItems = [];
        $bundleLines = [];
        // Expected POS items payload:
        // - product_id (int)
        // - qty (int)
        // - line_key (optional, string)
        // - bundle_picks (optional): [{ product_id, sku }]
        // - pack_selection (optional): { "<product_id>": <qty>, ... }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
            $packSelection = $item['pack_selection'] ?? null;
            $lineKey = isset($item['line_key']) ? sanitize_text_field((string) $item['line_key']) : '';
            $bundlePicksRaw = $item['bundle_picks'] ?? null;
            if ($productId <= 0 || $qty < 1) {
                continue;
            }
            $product = wc_get_product($productId);
            if (!$product) {
                continue;
            }
            $bundleMeta = $this->get_bundle_meta($productId);
            $selection = is_array($packSelection) ? $packSelection : null;
            if (!$bundleMeta['is_bundle']) {
                if (!$sellableService->is_sellable($productId, $qty, $selection)) {
                    $name = $product->get_name();
                    $message = $name !== '' ? ('Stock insuficiente: ' . $name . '.') : 'Stock insuficiente.';
                    $sellableService->log_blocked('pos_create_order', $productId, $qty, $selection);
                    $this->send_pos_error('pos_stock_insufficient', $message, 422);
                }
            }

            $bundlePayload = null;
            if ($bundleMeta['is_bundle']) {
                if ($lineKey === '') {
                    $this->send_pos_error('pos_bundle_missing_key', 'Bundle sin clave de línea.', 422);
                }
                if ($bundleMeta['bundle_qty'] <= 0) {
                    $this->send_pos_error('pos_bundle_invalid', 'Bundle sin configuración válida.', 422);
                }
                if ($qty !== 1) {
                    $this->send_pos_error('pos_bundle_invalid_qty', 'Los bundles deben añadirse de uno en uno.', 422);
                }
                if (!is_array($bundlePicksRaw)) {
                    $this->send_pos_error('pos_bundle_missing_picks', 'Selecciona los productos del bundle.', 422);
                }
                $allowedCategories = $this->parse_bundle_categories($bundleMeta['bundle_pick_category']);
                $rules = $bundleMeta['bundle_pick_rules'] !== '' ? $bundleMeta['bundle_pick_rules'] : 'any';
                $bundlePicks = [];
                $perProductQty = [];
                $totalQty = 0;
                foreach ($bundlePicksRaw as $pickRaw) {
                    if (!is_array($pickRaw)) {
                        $this->send_pos_error('pos_bundle_invalid_picks', 'Selección de bundle inválida.', 422);
                    }
                    $pickProductId = isset($pickRaw['product_id']) ? (int) $pickRaw['product_id'] : 0;
                    if ($pickProductId <= 0) {
                        $this->send_pos_error('pos_bundle_invalid_picks', 'Producto del bundle inválido.', 422);
                    }
                    $pickQty = isset($pickRaw['qty']) ? (int) $pickRaw['qty'] : 1;
                    if ($pickQty <= 0) {
                        $this->send_pos_error('pos_bundle_invalid_picks', 'Cantidad del bundle inválida.', 422);
                    }
                    $pickProduct = wc_get_product($pickProductId);
                    if (!$pickProduct) {
                        $this->send_pos_error('pos_bundle_invalid_picks', 'Producto del bundle no disponible.', 422);
                    }
                    if ($allowedCategories !== [] && !has_term($allowedCategories, 'product_cat', $pickProductId)) {
                        $this->send_pos_error('pos_bundle_invalid_picks', 'El producto seleccionado no pertenece a la categoría del bundle.', 422);
                    }
                    $pickSku = (string) $pickProduct->get_sku();
                    if ($pickSku === '') {
                        $pickSku = 'product-' . $pickProductId;
                    }
                    if ($rules !== 'any' && isset($bundlePicks[$pickSku])) {
                        $this->send_pos_error('pos_bundle_duplicate_pick', 'No puedes repetir el mismo SKU en el bundle.', 422);
                    }
                    $totalQty += $pickQty;
                    if (!isset($bundlePicks[$pickSku])) {
                        $bundlePicks[$pickSku] = [
                            'product_id' => $pickProductId,
                            'sku' => $pickSku,
                            'qty' => 0,
                            'name' => $pickProduct->get_name(),
                        ];
                    }
                    $bundlePicks[$pickSku]['qty'] += $pickQty;
                    $perProductQty[$pickProductId] = ($perProductQty[$pickProductId] ?? 0) + $pickQty;
                }
                if ($totalQty !== $bundleMeta['bundle_qty']) {
                    $this->send_pos_error('pos_bundle_invalid_picks', 'Debes seleccionar exactamente ' . $bundleMeta['bundle_qty'] . ' productos.', 422);
                }
                foreach ($perProductQty as $pickProductId => $pickQty) {
                    if (!$sellableService->is_sellable($pickProductId, $pickQty, null)) {
                        $pickProduct = wc_get_product($pickProductId);
                        $name = $pickProduct ? $pickProduct->get_name() : '';
                        $message = $name !== '' ? ('Stock insuficiente: ' . $name . '.') : 'Stock insuficiente.';
                        $sellableService->log_blocked('pos_bundle_pick', $pickProductId, $pickQty, null);
                        $this->send_pos_error('pos_stock_insufficient', $message, 422);
                    }
                }
                $bundlePayload = [
                    'parent_line_key' => $lineKey,
                    'bundle_qty' => $bundleMeta['bundle_qty'],
                    'bundle_pick_category' => $bundleMeta['bundle_pick_category'],
                    'bundle_pick_rules' => $rules,
                    'picks' => array_values($bundlePicks),
                ];
                $bundleLines[] = $bundlePayload;
            }
            $priceCents = (int) round(((float) $product->get_price()) * 100);
            $itemsTotalCents += $priceCents * $qty;
            $validatedItems[] = [
                'product' => $product,
                'qty' => $qty,
                'line_key' => $lineKey,
                'bundle' => $bundlePayload,
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
        $resolvedEmail = $customerEmail;
        $resolvedFirstName = $customerFirstName;
        $resolvedLastName = $customerLastName;
        if ($resolvedEmail === '' && $customerId > 0) {
            global $wpdb;
            $crmTable = $wpdb->prefix . 'bressol_crm_customers';
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $crmTable));
            if ($exists === $crmTable) {
                $crmRow = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT email, first_name, last_name FROM {$crmTable} WHERE id = %d LIMIT 1",
                        $customerId
                    )
                );
                if ($crmRow) {
                    $resolvedEmail = sanitize_email((string) ($crmRow->email ?? ''));
                    if ($resolvedFirstName === '') {
                        $resolvedFirstName = sanitize_text_field((string) ($crmRow->first_name ?? ''));
                    }
                    if ($resolvedLastName === '') {
                        $resolvedLastName = sanitize_text_field((string) ($crmRow->last_name ?? ''));
                    }
                }
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
            $itemId = $order->add_product($product, $item['qty']);
            if (!$itemId) {
                $this->send_pos_error('pos_order_item_failed', 'No se pudo añadir un producto al pedido.', 500);
            }
            $orderItem = $order->get_item($itemId);
            if ($orderItem instanceof \WC_Order_Item_Product) {
                $lineKey = isset($item['line_key']) ? (string) $item['line_key'] : '';
                if ($lineKey !== '') {
                    $orderItem->add_meta_data('_bressol_pos_line_key', $lineKey, true);
                }
                if (!empty($item['bundle']) && is_array($item['bundle'])) {
                    $orderItem->add_meta_data('_bressol_pos_bundle_picks', $item['bundle'], true);
                }
                $orderItem->save();
            }
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
        if ($resolvedEmail !== '' && is_email($resolvedEmail)) {
            $order->set_billing_email($resolvedEmail);
            if ($resolvedFirstName !== '') {
                $order->set_billing_first_name($resolvedFirstName);
            }
            if ($resolvedLastName !== '') {
                $order->set_billing_last_name($resolvedLastName);
            }
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
        $order->update_meta_data('_bressol_pos_sale', '1');
        $order->update_meta_data('_bressol_payment_method', $paymentMethod);
        if ($paymentReference !== '') {
            $order->update_meta_data('_bressol_payment_reference', $paymentReference);
        }

        $order->calculate_totals();

        if (class_exists(BundlePicksService::class) && $bundleLines !== []) {
            $bundleService = new BundlePicksService();
            foreach ($bundleLines as $bundleLine) {
                $lineKey = (string) ($bundleLine['parent_line_key'] ?? '');
                $picks = is_array($bundleLine['picks'] ?? null) ? $bundleLine['picks'] : [];
                if ($lineKey === '' || $picks === []) {
                    continue;
                }
                try {
                    $bundleService->record_picks((int) $order->get_id(), (int) $eventId, $lineKey, $picks);
                } catch (\Throwable $exception) {
                    $order->update_meta_data('_bressol_pos_bundle_failed', '1');
                    $order->update_meta_data('_bressol_pos_bundle_failed_reason', substr($exception->getMessage(), 0, 120));
                    $order->save();
                    $this->send_pos_error('pos_bundle_picks_failed', 'No se pudieron registrar los picks del bundle.', 500);
                }
            }
        }

        $netExVatCents = 0;
        $estimated = false;
        $cogsCents = 0;
        $costService = new CostMarginService();
        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $qty = (int) $item->get_quantity();
            if ($qty <= 0) {
                continue;
            }
            $lineTotal = (float) $item->get_total();
            $netExVatCents += (int) round($lineTotal * 100);
            $productId = (int) $item->get_product_id();
            if ($productId <= 0) {
                $estimated = true;
                continue;
            }
            $unitCost = $costService->get_unit_cost_cents($productId, 'NL', current_time('Y-m-d'));
            if ($unitCost === null) {
                $estimated = true;
                $unitCost = 0;
            }
            $cogsCents += $unitCost * $qty;
        }
        $profitCents = $netExVatCents - $cogsCents;
        $order->update_meta_data('_bressol_pos_net_ex_vat_cents', $netExVatCents);
        $order->update_meta_data('_bressol_pos_cogs_estimated_cents', $cogsCents);
        $order->update_meta_data('_bressol_pos_profit_estimated_cents', $profitCents);
        $order->update_meta_data('_bressol_pos_profit_is_estimated', $estimated ? '1' : '0');

        $order->set_status('completed');
        $order->save();
        $this->maybe_send_pos_transactional_emails($order, $auditLogger);

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

    private function maybe_send_pos_transactional_emails(\WC_Order $order, ?AuditLogger $auditLogger = null): void
    {
        if ($order->get_meta('_bressol_pos_tx_emails_sent', true)) {
            return;
        }

        if (!function_exists('WC')) {
            return;
        }

        $mailer = WC()->mailer();
        if (!$mailer) {
            return;
        }

        $emails = $mailer->get_emails();
        $orderId = $order->get_id();
        $billingEmail = $order->get_billing_email();
        $sentAny = false;

        $adminEmail = $emails['WC_Email_New_Order'] ?? null;
        if ($adminEmail instanceof \WC_Email && $adminEmail->is_enabled()) {
            $adminEmail->trigger($orderId, $order);
            $sentAny = true;
        }

        if ($billingEmail !== '' && is_email($billingEmail)) {
            if ($order->get_status() === 'completed') {
                $completedEmail = $emails['WC_Email_Customer_Completed_Order'] ?? null;
                if ($completedEmail instanceof \WC_Email && $completedEmail->is_enabled()) {
                    $completedEmail->trigger($orderId, $order);
                    $sentAny = true;
                }
            } else {
                $processingEmail = $emails['WC_Email_Customer_Processing_Order'] ?? null;
                if ($processingEmail instanceof \WC_Email && $processingEmail->is_enabled()) {
                    $processingEmail->trigger($orderId, $order);
                    $sentAny = true;
                }
            }
        }

        if ($sentAny) {
            $order->update_meta_data('_bressol_pos_tx_emails_sent', current_time('mysql'));
            $order->save_meta_data();
            if ($auditLogger instanceof AuditLogger) {
                $auditLogger->log('pos_tx_email_sent', 'order', $orderId, get_current_user_id(), [
                    'email' => $billingEmail,
                ]);
            }
        }
    }

    public function handleOpenSamplingItemAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_open_sampling_item', 'nonce');
        $this->rate_limit_or_fail('open_sampling_item', 20, 20);
        $this->require_pos_session_for_today(get_current_user_id(), false);

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
            $message = $exception->getMessage() === 'event not eligible'
                ? 'Evento no elegible hoy.'
                : 'Evento inválido.';
            $code = $exception->getMessage() === 'event not eligible'
                ? 'pos_event_not_eligible'
                : 'pos_invalid_event';
            $this->send_pos_error($code, $message, 422);
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
        $this->require_pos_session_for_today(get_current_user_id(), false);

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
        $this->require_pos_session_for_today(get_current_user_id(), false);

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

    public function handleBootstrapAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_bootstrap', 'nonce');

        $today = $this->get_today_date();
        $userId = get_current_user_id();
        $context = new PosEventContextService();
        $activeEventId = $context->get_active_event_id_for_today($userId, $today);
        $debugFiltered = [];
        $eventsToday = $this->list_events_today($debugFiltered);

        if ($activeEventId !== null) {
            $exists = false;
            foreach ($eventsToday as $event) {
                if ((int) ($event['id'] ?? 0) === (int) $activeEventId) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $activeEventId = null;
            }
        }

        $needsEventSelect = $activeEventId === null;
        $needsSamplingControl = !$needsEventSelect && !$this->is_sampling_control_done($userId, (int) $activeEventId, $today);
        $registerClosed = !$needsEventSelect && $this->is_register_closed($userId, (int) $activeEventId, $today);

        $payload = [
            'today' => $today,
            'active_event_id' => $activeEventId,
            'needs_event_select' => $needsEventSelect,
            'needs_sampling_control' => $needsSamplingControl,
            'register_closed' => $registerClosed,
            'events_today' => $eventsToday,
        ];
        if (defined('WP_DEBUG') && WP_DEBUG && $debugFiltered !== []) {
            $payload['debug_filtered'] = $debugFiltered;
        }

        wp_send_json_success($payload);
    }

    public function handleSetActiveEventAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_set_active_event', 'nonce');

        $eventId = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        if ($eventId <= 0) {
            $this->send_pos_error('pos_invalid_event', 'Evento inválido.', 422);
        }

        $repo = new EventRepository();
        $event = $repo->find_by_id($eventId);
        if (!$event) {
            $this->send_pos_error('pos_invalid_event', 'Evento no encontrado.', 404);
        }

        $eligibility = new PosEventEligibilityService($repo);
        if (!$eligibility->is_event_eligible_for_pos($event)) {
            $this->send_pos_error('pos_event_not_eligible', 'Evento no elegible hoy.', 422);
        }

        $today = $this->get_today_date();
        $context = new PosEventContextService($repo, $eligibility);
        $context->set_active_event_for_today(get_current_user_id(), $eventId, $today);

        wp_send_json_success([
            'ok' => true,
            'event_id' => $eventId,
            'needs_sampling_control' => true,
        ]);
    }

    public function handleMarkSamplingControlDoneAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_mark_sampling_control_done', 'nonce');

        $today = $this->get_today_date();
        $eventId = $this->require_pos_session_for_today(get_current_user_id(), false);
        $this->mark_sampling_control_done(get_current_user_id(), $eventId, $today);

        wp_send_json_success([
            'ok' => true,
            'event_id' => $eventId,
        ]);
    }

    public function handleCompleteSamplingControlAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_complete_sampling_control', 'nonce');

        $today = $this->get_today_date();
        $eventId = $this->require_pos_session_for_today(get_current_user_id(), false);
        $this->mark_sampling_control_done(get_current_user_id(), $eventId, $today);

        wp_send_json_success([
            'ok' => true,
            'event_id' => $eventId,
        ]);
    }

    public function handleDashboardAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_dashboard', 'nonce');

        $userId = get_current_user_id();
        $eventId = $this->require_pos_session_for_today($userId, false, false);
        $today = $this->get_today_date();

        $orders = $this->list_pos_orders_for_event_day($eventId, $today);
        $profitSum = 0;
        $totalsByPayment = [
            'cash' => 0,
            'pin' => 0,
            'tikkie' => 0,
        ];

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }
            $profit = $order->get_meta('_bressol_pos_profit_estimated_cents', true);
            $profitSum += is_numeric($profit) ? (int) $profit : 0;

            $method = (string) $order->get_meta('_bressol_payment_method', true);
            $totalCents = (int) round(((float) $order->get_total()) * 100);
            if (isset($totalsByPayment[$method])) {
                $totalsByPayment[$method] += $totalCents;
            }
        }

        $dailyCost = (new EventCostService())->get_event_daily_cost_cents($eventId);
        $profitLive = $profitSum - $dailyCost;
        $label = $this->resolve_profit_label($profitLive);
        $breakEven = $profitLive < 0
            ? ['status' => 'missing', 'missing_cents' => abs($profitLive)]
            : ['status' => 'covered', 'missing_cents' => 0];

        wp_send_json_success([
            'profit_live_cents' => $profitLive,
            'label' => $label,
            'break_even_status' => $breakEven,
            'totals_by_payment_method' => $totalsByPayment,
        ]);
    }

    public function handleCloseRegisterAjax(): void
    {
        if (!current_user_can($this->get_capability())) {
            $this->send_pos_error('pos_forbidden', 'No autorizado.', 403);
        }

        check_ajax_referer('bressol_pos_close_register', 'nonce');

        $userId = get_current_user_id();
        $today = $this->get_today_date();
        $eventId = $this->require_pos_session_for_today($userId, true, false);

        if ($this->is_register_closed($userId, $eventId, $today)) {
            $this->send_pos_error('POS_REGISTER_CLOSED', 'La caja ya está cerrada hoy.', 409);
        }

        $counted = isset($_POST['counted_cash_cents']) ? (int) wp_unslash($_POST['counted_cash_cents']) : null;
        $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
        if ($counted === null) {
            $this->send_pos_error('pos_invalid_cash', 'Importe contado inválido.', 422);
        }

        $orders = $this->list_pos_orders_for_event_day($eventId, $today);
        $expected = 0;
        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }
            $method = (string) $order->get_meta('_bressol_payment_method', true);
            if ($method !== 'cash') {
                continue;
            }
            $expected += (int) round(((float) $order->get_total()) * 100);
        }

        $delta = $counted - $expected;
        $this->mark_register_closed($userId, $eventId, $today, [
            'expected_cash_cents' => $expected,
            'counted_cash_cents' => $counted,
            'delta_cents' => $delta,
            'note' => $note,
            'closed_at' => current_time('mysql'),
        ]);

        (new PosSalesSnapshotBuilder())->build_for_event($eventId);

        wp_send_json_success([
            'ok' => true,
            'event_id' => $eventId,
            'expected_cash_cents' => $expected,
            'counted_cash_cents' => $counted,
            'delta_cents' => $delta,
        ]);
    }

    private function get_capability(): string
    {
        return class_exists('WooCommerce') ? 'manage_woocommerce' : 'manage_options';
    }

    private function require_pos_session_for_today(int $userId, bool $requireSampling, bool $requireOpenRegister = true): int
    {
        $today = $this->get_today_date();
        $context = new PosEventContextService();
        $eventId = $context->get_active_event_id_for_today($userId, $today);
        if ($eventId === null || $eventId <= 0) {
            $this->send_pos_error('POS_NO_ACTIVE_EVENT', 'No hay evento activo para hoy.', 409);
        }

        if ($requireSampling && !$this->is_sampling_control_done($userId, $eventId, $today)) {
            $this->send_pos_error('POS_NEEDS_SAMPLING_CONTROL', 'Falta control de sampling.', 409);
        }

        if ($requireOpenRegister && $this->is_register_closed($userId, $eventId, $today)) {
            $this->send_pos_error('POS_REGISTER_CLOSED', 'La caja está cerrada hoy.', 409);
        }

        return (int) $eventId;
    }

    /** @return \WC_Order[] */
    private function list_pos_orders_for_event_day(int $eventId, string $today): array
    {
        if ($eventId <= 0 || $today === '' || !function_exists('wc_get_orders')) {
            return [];
        }

        $tz = new \DateTimeZone('Europe/Amsterdam');
        $day = \DateTimeImmutable::createFromFormat('Y-m-d', $today, $tz);
        if (!$day || $day->format('Y-m-d') !== $today) {
            $this->send_pos_error('POS_BAD_DATE', 'Fecha inválida.', 422);
        }
        $start = $day->setTime(0, 0, 0);
        $end = $day->setTime(23, 59, 59);
        $range = $start->format('Y-m-d H:i:s') . '...' . $end->format('Y-m-d H:i:s');

        $orders = [];
        $page = 1;
        $limit = 200;
        do {
            $ids = wc_get_orders([
                'status' => 'completed',
                'return' => 'ids',
                'limit' => $limit,
                'paged' => $page,
                'date_created' => $range,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_bressol_pos_channel',
                        'value' => 'pos',
                        'compare' => '=',
                    ],
                    [
                        'key' => '_bressol_event_id',
                        'value' => (string) $eventId,
                        'compare' => '=',
                    ],
                ],
            ]);

            if ($ids === []) {
                break;
            }

            foreach ($ids as $orderId) {
                $order = wc_get_order($orderId);
                if ($order instanceof \WC_Order) {
                    $orders[] = $order;
                }
            }

            $page++;
        } while (count($ids) === $limit && $page <= 20);

        return $orders;
    }

    private function resolve_profit_label(int $profitCents): string
    {
        if ($profitCents < 0) {
            return 'fatal';
        }
        if ($profitCents < 7000) {
            return 'malo';
        }
        if ($profitCents < 15000) {
            return 'regular';
        }
        if ($profitCents < 22000) {
            return 'bueno';
        }
        return 'perfecto';
    }

    private function is_sampling_control_done(int $userId, int $eventId, string $today): bool
    {
        if ($userId <= 0 || $eventId <= 0 || $today === '') {
            return false;
        }

        $key = 'bressol_pos_sampling_control_done_' . $eventId . '_' . $today;
        return get_user_meta($userId, $key, true) !== '';
    }

    private function mark_sampling_control_done(int $userId, int $eventId, string $today): void
    {
        if ($userId <= 0 || $eventId <= 0 || $today === '') {
            return;
        }

        $key = 'bressol_pos_sampling_control_done_' . $eventId . '_' . $today;
        update_user_meta($userId, $key, '1');
    }

    private function is_register_closed(int $userId, int $eventId, string $today): bool
    {
        if ($userId <= 0 || $eventId <= 0 || $today === '') {
            return false;
        }

        $key = 'bressol_pos_cash_close_' . $eventId . '_' . $today;
        return get_user_meta($userId, $key, true) !== '';
    }

    /** @param array<string, mixed> $payload */
    private function mark_register_closed(int $userId, int $eventId, string $today, array $payload): void
    {
        if ($userId <= 0 || $eventId <= 0 || $today === '') {
            return;
        }

        $key = 'bressol_pos_cash_close_' . $eventId . '_' . $today;
        update_user_meta($userId, $key, $payload);
    }

    private function get_today_date(): string
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        return (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
    }

    /** @return array<int, array<string, mixed>> */
    private function list_events_today(array &$debugFiltered = []): array
    {
        $eligibility = new PosEventEligibilityService(new EventRepository());
        $events = $eligibility->list_events_today_with_debug(200, $debugFiltered);
        if ($events === []) {
            return [];
        }

        $out = [];
        foreach ($events as $event) {
            $out[] = $this->build_event_summary($event);
        }

        return $out;
    }

    /** @param array<string, mixed> $event */
    private function build_event_summary(array $event): array
    {
        $eventId = (int) ($event['id'] ?? 0);
        $title = trim((string) ($event['title'] ?? ''));
        $location = trim((string) ($event['location_name'] ?? ''));
        $name = $title;
        if ($location !== '') {
            $name = $title !== '' ? $title . ' - ' . $location : $location;
        }
        if ($name === '') {
            $name = 'Event ' . $eventId;
        }

        $city = isset($event['city']) ? (string) $event['city'] : '';
        if ($city === '') {
            $city = $this->extract_event_city((string) ($event['address'] ?? ''));
        }
        if ($city === '' && $location !== '') {
            $city = $location;
        }

        return [
            'id' => $eventId,
            'name' => $name,
            'city' => $city,
            'start_date' => $this->format_event_date((string) ($event['start_at'] ?? '')),
            'end_date' => $this->format_event_date((string) ($event['end_at'] ?? '')),
        ];
    }

    private function format_event_date(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $tz);
        if (!$dt) {
            return '';
        }

        return $dt->format('Y-m-d');
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

    /** @return array{sent:bool,error:string} */
    private function send_pos_welcome_email(string $email, string $firstName, string $city, ?array $event): array
    {
        // POS transactional email — NEVER use ESP
        if (!class_exists(EspSettings::class)) {
            return ['sent' => false, 'error' => 'esp_settings_missing'];
        }

        $greetingName = $firstName !== '' ? $firstName : 'hola';
        $missing = [];
        if ($firstName === '') {
            $missing[] = 'nombre';
        }
        $missingText = '';
        if ($missing !== []) {
            $missingText = '<p>Si puedes, respóndenos con tu ' . esc_html(implode(' y ', $missing)) . '.</p>';
        }

        $subject = '¡Gracias por unirte a Bressol!';
        $body = '<p>¡Gracias por unirte a Bressol, ' . esc_html($greetingName) . '!</p>';
        if (is_array($event) && !empty($event['name'])) {
            $eventCity = isset($event['city']) && $event['city'] !== '' ? (' (' . esc_html((string) $event['city']) . ')') : '';
            $body .= '<p>Gracias por visitarnos en ' . esc_html((string) $event['name']) . $eventCity . '.</p>';
        }
        $body .= '<p>Hemos añadido 300 semillas de bienvenida a tu cuenta.</p>';
        $body .= $missingText;
        $body .= '<p>¡Nos vemos pronto!</p>';

        EspSettings::set_smtp_allowed(true);
        $mailError = '';
        $listener = static function ($wpError) use (&$mailError): void {
            if ($wpError instanceof \WP_Error) {
                $mailError = $wpError->get_error_code();
                return;
            }
            $mailError = 'wp_mail_failed';
        };
        add_action('wp_mail_failed', $listener);
        $sent = false;
        try {
            $sent = (bool) wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        } finally {
            remove_action('wp_mail_failed', $listener);
            EspSettings::set_smtp_allowed(false);
        }
        if (!$sent && $mailError === '') {
            $mailError = 'wp_mail_failed';
        }
        return ['sent' => $sent, 'error' => $mailError];
    }

    private function resolve_active_event_context(): ?array
    {
        if (!class_exists(PosEventContextService::class) || !class_exists(EventRepository::class)) {
            return null;
        }

        $eventId = (new PosEventContextService())->get_active_event_id(get_current_user_id());
        if (!$eventId) {
            return null;
        }

        return $this->get_event_context_by_id((int) $eventId);
    }

    private function get_event_context_by_id(int $eventId): ?array
    {
        if ($eventId <= 0 || !class_exists(EventRepository::class)) {
            return null;
        }

        $repo = new EventRepository();
        $event = $repo->find_by_id($eventId);
        if (!$event) {
            return null;
        }

        $title = trim((string) ($event['title'] ?? ''));
        $location = trim((string) ($event['location_name'] ?? ''));
        $name = $title;
        if ($location !== '') {
            $name = $title !== '' ? $title . ' - ' . $location : $location;
        }
        $city = isset($event['city']) ? (string) $event['city'] : '';
        if ($city === '') {
            $city = $this->extract_event_city((string) ($event['address'] ?? ''));
        }
        if ($city === '' && $location !== '') {
            $city = $location;
        }

        return [
            'id' => $eventId,
            'name' => $name !== '' ? $name : ('Event ' . $eventId),
            'city' => $city,
        ];
    }

    private function extract_event_city(string $address): string
    {
        $address = trim($address);
        if ($address === '') {
            return '';
        }
        $parts = array_map('trim', explode(',', $address));
        $last = (string) end($parts);
        return trim($last);
    }

    /** @return array{is_bundle:bool,bundle_qty:int,bundle_pick_category:string,bundle_pick_rules:string} */
    private function get_bundle_meta(int $productId): array
    {
        if ($productId <= 0) {
            return [
                'is_bundle' => false,
                'bundle_qty' => 0,
                'bundle_pick_category' => '',
                'bundle_pick_rules' => 'any',
            ];
        }

        $rawIsBundle = (string) get_post_meta($productId, 'is_bundle', true);
        $rawQty = (string) get_post_meta($productId, 'bundle_qty', true);
        $rawCategory = (string) get_post_meta($productId, 'bundle_pick_category', true);
        $rawRules = (string) get_post_meta($productId, 'bundle_pick_rules', true);

        $isBundle = in_array($rawIsBundle, ['1', 'yes', 'true'], true) || (int) $rawIsBundle === 1;
        $bundleQty = max(0, (int) $rawQty);
        $rules = sanitize_key($rawRules);
        if ($rules === '') {
            $rules = 'any';
        }

        return [
            'is_bundle' => $isBundle,
            'bundle_qty' => $bundleQty,
            'bundle_pick_category' => trim($rawCategory),
            'bundle_pick_rules' => $rules,
        ];
    }

    /** @return string[] */
    private function parse_bundle_categories(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[|,]/', $raw) ?: [];
        $slugs = [];
        foreach ($parts as $part) {
            $slug = sanitize_title((string) $part);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
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
