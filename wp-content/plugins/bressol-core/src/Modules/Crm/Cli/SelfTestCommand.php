<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Cli;

use Bressol\Modules\Crm\Services\CustomerService;
use Bressol\Modules\Crm\Services\PointsService;
use Bressol\Modules\Esp\Services\EspConsentService;

if (!defined('ABSPATH')) {
    exit;
}

final class SelfTestCommand
{
    private CustomerService $customerService;
    private PointsService $pointsService;
    private ?EspConsentService $espConsentService;

    public function __construct(CustomerService $customerService, PointsService $pointsService, ?EspConsentService $espConsentService)
    {
        $this->customerService = $customerService;
        $this->pointsService = $pointsService;
        $this->espConsentService = $espConsentService;
    }

    /**
     * Ejecuta self-test del CRM (sin PII en salida).
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        global $wpdb;

        $results = [];
        $customerIds = [];
        $orderIds = [];
        $suffix = substr(wp_generate_uuid4(), 0, 8);

        $createCustomer = function (string $status, int $loyaltyEnabled, string $label) use (&$wpdb, &$customerIds, $suffix): int {
            $table = $wpdb->prefix . 'bressol_crm_customers';
            $email = 'crm-selftest-' . $label . '-' . $suffix . '@example.invalid';
            $now = current_time('mysql');

            $wpdb->insert(
                $table,
                [
                    'email' => $email,
                    'customer_type' => 'b2c',
                    'status' => $status,
                    'source' => 'selftest',
                    'can_be_profiled' => 0,
                    'can_receive_marketing' => 0,
                    'loyalty_enabled' => $loyaltyEnabled,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
            );

            $customerId = (int) $wpdb->insert_id;
            $customerIds[] = $customerId;
            return $customerId;
        };

        $makeOrder = static function (int $orderId, float $total, array $meta = []): object {
            return new class ($orderId, $total, $meta) {
                private int $orderId;
                private float $total;
                /** @var array<string, mixed> */
                private array $meta;

                /** @param array<string, mixed> $meta */
                public function __construct(int $orderId, float $total, array $meta)
                {
                    $this->orderId = $orderId;
                    $this->total = $total;
                    $this->meta = $meta;
                }

                public function get_id(): int
                {
                    return $this->orderId;
                }

                public function get_total(): float
                {
                    return $this->total;
                }

                public function get_meta(string $key)
                {
                    return $this->meta[$key] ?? null;
                }
            };
        };

        $runCase = function (string $label, callable $callback) use (&$results): void {
            try {
                $ok = (bool) $callback();
            } catch (\Throwable $e) {
                $ok = false;
            }

            $results[] = [
                'label' => $label,
                'ok' => $ok,
            ];
        };

        $runCase('Loyalty OFF -> no points ledger + audit skip', function () use ($createCustomer, $makeOrder, &$orderIds, &$wpdb): bool {
            $customerId = $createCustomer('active', 0, 'loyalty-off');
            $orderId = random_int(1000000, 9999999);
            $orderIds[] = $orderId;
            $order = $makeOrder($orderId, 120.0);

            $awarded = $this->pointsService->award_points_for_order($customerId, $order, 'b2c', 'selftest');
            if ($awarded) {
                return false;
            }

            $ledgerTable = $wpdb->prefix . 'bressol_crm_points_ledger';
            $ledgerExists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$ledgerTable} WHERE customer_id = %d AND source_type = %s AND source_id = %d LIMIT 1",
                    $customerId,
                    'order',
                    $orderId
                )
            );

            if ($ledgerExists !== null) {
                return false;
            }

            $auditTable = $wpdb->prefix . 'bressol_crm_audit_logs';
            $contextJson = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT context FROM {$auditTable} WHERE action = %s AND entity_type = %s AND entity_id = %d ORDER BY id DESC LIMIT 1",
                    'points_skipped',
                    'customer',
                    $customerId
                )
            );

            if (!$contextJson) {
                return false;
            }

            $context = json_decode((string) $contextJson, true);
            if (!is_array($context)) {
                return false;
            }

            return ($context['reason_code'] ?? null) === 'points_skipped_no_loyalty';
        });

        $runCase('Loyalty ON + active -> points ledger idempotent', function () use ($createCustomer, $makeOrder, &$orderIds, &$wpdb): bool {
            $customerId = $createCustomer('active', 1, 'loyalty-on');
            $orderId = random_int(1000000, 9999999);
            $orderIds[] = $orderId;
            $order = $makeOrder($orderId, 80.0);

            $first = $this->pointsService->award_points_for_order($customerId, $order, 'b2c', 'selftest');
            $second = $this->pointsService->award_points_for_order($customerId, $order, 'b2c', 'selftest');

            $ledgerTable = $wpdb->prefix . 'bressol_crm_points_ledger';
            $ledgerCount = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$ledgerTable} WHERE customer_id = %d AND source_type = %s AND source_id = %d",
                    $customerId,
                    'order',
                    $orderId
                )
            );

            return $first && !$second && $ledgerCount === 1;
        });

        $runCase('ESP opt_out -> marketing opt-in blocked', function () use ($createCustomer, $makeOrder, &$orderIds, &$wpdb): bool {
            if (!$this->espConsentService instanceof EspConsentService) {
                return false;
            }

            $customerId = $createCustomer('active', 0, 'esp-optout');
            $customer = $this->customerService->get_customer($customerId);
            if (!$customer || (string) $customer->email === '') {
                return false;
            }

            $email = (string) $customer->email;
            $this->espConsentService->set_consent_status($email, 'opt_out', 'selftest', null);

            $orderId = random_int(1000000, 9999999);
            $orderIds[] = $orderId;
            $order = $makeOrder($orderId, 50.0, ['_bressol_marketing_opt_in' => 'yes']);

            $this->customerService->apply_marketing_opt_in_from_order($customerId, $order);

            $freshCustomer = $this->customerService->get_customer($customerId);
            if (!$freshCustomer) {
                return false;
            }

            if ((int) $freshCustomer->can_receive_marketing !== 0) {
                return false;
            }

            $espStatus = $this->customerService->get_esp_consent_status($email);
            return $espStatus === 'opt_out';
        });

        $runCase('Redemption blocked without loyalty or inactive', function () use ($createCustomer): bool {
            $customerNoLoyalty = $createCustomer('active', 0, 'redeem-no-loyalty');
            $resultNoLoyalty = $this->pointsService->create_redemption($customerNoLoyalty, 10, 'selftest', null, null);

            $customerInactive = $createCustomer('deleted', 1, 'redeem-inactive');
            $resultInactive = $this->pointsService->create_redemption($customerInactive, 10, 'selftest', null, null);

            return $resultNoLoyalty === 0 && $resultInactive === 0;
        });

        $runCase('Anonymized/deleted block PII + loyalty updates', function () use ($createCustomer): bool {
            $customerId = $createCustomer('active', 1, 'anonymized');
            if (!$this->customerService->anonymize_customer($customerId)) {
                return false;
            }

            $resultPii = $this->customerService->update_from_admin($customerId, ['first_name' => 'Test']);
            if (!empty($resultPii['ok'])) {
                return false;
            }

            $resultLoyalty = $this->customerService->update_from_admin($customerId, ['loyalty_enabled' => 0]);
            if (!empty($resultLoyalty['ok'])) {
                return false;
            }

            $customerDeleted = $createCustomer('active', 1, 'deleted');
            if (!$this->customerService->soft_delete_customer($customerDeleted, null)) {
                return false;
            }

            $resultDeleted = $this->customerService->update_from_admin($customerDeleted, ['first_name' => 'Test']);
            return empty($resultDeleted['ok']);
        });

        foreach ($results as $result) {
            $status = $result['ok'] ? 'PASS' : 'FAIL';
            \WP_CLI::log($status . ' - ' . $result['label']);
        }

        $failed = array_filter($results, static fn (array $row): bool => !$row['ok']);
        if ($failed !== []) {
            \WP_CLI::warning('Selftest finalizó con fallos.');
        } else {
            \WP_CLI::success('Selftest completado sin fallos.');
        }

        $this->cleanup($customerIds, $orderIds);
    }

    /** @param int[] $customerIds
     *  @param int[] $orderIds
     */
    private function cleanup(array $customerIds, array $orderIds): void
    {
        if ($customerIds === []) {
            return;
        }

        global $wpdb;

        $customerIdsSql = implode(',', array_map('intval', $customerIds));

        $wpdb->query("DELETE FROM {$wpdb->prefix}bressol_crm_customers WHERE id IN ({$customerIdsSql})");
        $wpdb->query("DELETE FROM {$wpdb->prefix}bressol_crm_points_ledger WHERE customer_id IN ({$customerIdsSql})");
        $wpdb->query("DELETE FROM {$wpdb->prefix}bressol_crm_points_redemptions WHERE customer_id IN ({$customerIdsSql})");
        $wpdb->query("DELETE FROM {$wpdb->prefix}bressol_crm_customer_meta WHERE customer_id IN ({$customerIdsSql})");
        $wpdb->query("DELETE FROM {$wpdb->prefix}bressol_crm_customer_tags WHERE customer_id IN ({$customerIdsSql})");
        $wpdb->query("DELETE FROM {$wpdb->prefix}bressol_crm_audit_logs WHERE entity_type = 'customer' AND entity_id IN ({$customerIdsSql})");

        if ($orderIds !== []) {
            $orderIdsSql = implode(',', array_map('intval', $orderIds));
            $wpdb->query("DELETE FROM {$wpdb->prefix}bressol_crm_order_sync WHERE order_id IN ({$orderIdsSql})");
        }
    }
}
