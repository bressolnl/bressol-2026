<?php
declare(strict_types=1);

namespace Bressol\Modules\Crm\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportCustomersService
{
    private CustomerService $customerService;
    private PointsService $pointsService;

    public function __construct(CustomerService $customerService, PointsService $pointsService)
    {
        $this->customerService = $customerService;
        $this->pointsService = $pointsService;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function run(array $options): array
    {
        if (!function_exists('wc_get_orders')) {
            return [
                'error' => 'WooCommerce no está disponible.',
            ];
        }

        $logger = isset($options['logger']) && is_callable($options['logger'])
            ? $options['logger']
            : null;

        $status = isset($options['status']) && $options['status'] !== null ? (string) $options['status'] : 'completed,processing';
        $statuses = array_filter(array_map('sanitize_text_field', explode(',', $status)));
        if ($statuses === []) {
            $statuses = ['completed', 'processing'];
        }

        $after = isset($options['after']) ? (string) $options['after'] : '';
        $before = isset($options['before']) ? (string) $options['before'] : '';
        if ($after !== '' && strtotime($after) === false) {
            return [
                'error' => 'Formato inválido para --after. Usa YYYY-MM-DD.',
            ];
        }
        if ($before !== '' && strtotime($before) === false) {
            return [
                'error' => 'Formato inválido para --before. Usa YYYY-MM-DD.',
            ];
        }

        $limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 200;
        $offset = isset($options['offset']) ? max(0, (int) $options['offset']) : 0;
        $dryRun = !empty($options['dry_run']);
        $withPoints = !empty($options['with_points']);
        $verbose = !empty($options['verbose']);

        $queryArgs = [
            'status' => $statuses,
            'limit' => $limit,
            'offset' => $offset,
            'return' => 'ids',
        ];

        if ($after !== '' || $before !== '') {
            $dateCreated = [
                'inclusive' => true,
            ];
            if ($after !== '') {
                $dateCreated['after'] = $after;
            }
            if ($before !== '') {
                $dateCreated['before'] = $before;
            }
            $queryArgs['date_created'] = $dateCreated;
        }

        $orderIds = wc_get_orders($queryArgs);
        if (!is_array($orderIds)) {
            return [
                'error' => 'No se pudieron obtener pedidos.',
            ];
        }

        $totals = [
            'orders_processed' => 0,
            'customers_created' => 0,
            'customers_updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($orderIds as $orderId) {
            $order = wc_get_order((int) $orderId);
            if (!$order) {
                $totals['skipped']++;
                $this->log($logger, 'Pedido inválido: #' . (int) $orderId, 'error');
                continue;
            }

            $email = method_exists($order, 'get_billing_email') ? sanitize_email((string) $order->get_billing_email()) : '';
            if ($email === '') {
                $totals['skipped']++;
                $this->log($logger, 'Pedido sin email: #' . (int) $orderId, 'error');
                continue;
            }

            $company = method_exists($order, 'get_billing_company')
                ? sanitize_text_field((string) $order->get_billing_company())
                : '';
            $customerType = $company !== '' ? 'b2b' : 'b2c';

            $existing = $this->customerService->find_customer_by_email_type($email, $customerType);

            try {
                if (!$dryRun) {
                    $customerId = $this->customerService->upsert_from_order($order);
                    if (!$customerId) {
                        $totals['errors']++;
                        $this->log($logger, 'No se pudo crear/actualizar cliente para el pedido #' . (int) $orderId, 'error');
                        continue;
                    }

                    $this->customerService->update_metrics_from_order($customerId, $order);
                    if ($withPoints) {
                        $this->pointsService->award_points_for_order(
                            $customerId,
                            $order,
                            $this->customerService->get_customer_type($customerId)
                        );
                    }
                }

                if ($existing) {
                    $totals['customers_updated']++;
                } else {
                    $totals['customers_created']++;
                }

                $totals['orders_processed']++;

                $emailForLog = $verbose ? $email : $this->mask_email($email);
                $this->log($logger, 'Procesado pedido #' . (int) $orderId . ' (' . $emailForLog . ')', 'info');
            } catch (\Throwable $exception) {
                $totals['errors']++;
                $this->log($logger, 'Error procesando pedido #' . (int) $orderId . ': ' . $exception->getMessage(), 'error');
                continue;
            }
        }

        return [
            'totals' => $totals,
            'offset' => $offset,
            'limit' => $limit,
            'batch_count' => count($orderIds),
        ];
    }

    private function log(?callable $logger, string $message, string $level): void
    {
        if ($logger === null) {
            return;
        }

        $logger($message, $level);
    }

    private function mask_email(string $email): string
    {
        if ($email === '') {
            return 'sin-email';
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return 'email-oculto';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $prefix = substr($local, 0, 2);
        if ($prefix === '') {
            $prefix = '*';
        }

        return $prefix . '***@' . $domain;
    }
}
