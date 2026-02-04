<?php
declare(strict_types=1);

namespace Bressol\Modules\CostMargin\Admin;

use Bressol\Modules\CostMargin\Transport\Services\TransportAllocator;
use Bressol\Modules\Inventory\Transfers\Repositories\TransferRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class Actions
{
    private const META_UNIT_COST = '_bressol_default_unit_cogs_cents';
    private const META_UNIT_WEIGHT = '_bressol_unit_weight_grams';

    public function register(): void
    {
        add_action('admin_post_bressol_cost_defaults_save', [$this, 'handle_cost_defaults_save']);
        add_action('admin_post_bressol_transport_snapshot_create', [$this, 'handle_transport_snapshot_create']);
        add_action('admin_post_bressol_transport_snapshot_recalc', [$this, 'handle_transport_snapshot_recalc']);
        add_action('admin_post_bressol_transport_snapshot_close', [$this, 'handle_transport_snapshot_close']);
    }

    public function handle_cost_defaults_save(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('bressol-cost-defaults', 'forbidden');
        }

        check_admin_referer('bressol_cost_defaults_save');

        $productIdsRaw = isset($_POST['product_ids']) ? wp_unslash($_POST['product_ids']) : [];
        $productIdsRaw = is_array($productIdsRaw) ? $productIdsRaw : [];
        $productIds = array_map('absint', $productIdsRaw);

        $costsRaw = isset($_POST['cost']) ? wp_unslash($_POST['cost']) : [];
        $costs = is_array($costsRaw) ? $costsRaw : [];

        $weightsRaw = isset($_POST['weight']) ? wp_unslash($_POST['weight']) : [];
        $weights = is_array($weightsRaw) ? $weightsRaw : [];
        $query = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';

        $invalid = 0;
        $updated = 0;

        foreach ($productIds as $productId) {
            if ($productId <= 0) {
                continue;
            }

            $product = get_post($productId);
            if (!$product || $product->post_type !== 'product') {
                $invalid++;
                continue;
            }

            $rawCost = $costs[$productId] ?? null;
            $cost = $this->normalize_int_strict($rawCost);
            if ($cost === null || $cost <= 0 || $cost > 10000000) {
                $invalid++;
                continue;
            }

            update_post_meta($productId, self::META_UNIT_COST, $cost);
            $updated++;

            if (array_key_exists($productId, $weights)) {
                $rawWeight = $weights[$productId];
                $rawWeight = is_string($rawWeight) ? trim($rawWeight) : $rawWeight;
                if ($rawWeight === '') {
                    delete_post_meta($productId, self::META_UNIT_WEIGHT);
                    continue;
                }

                $weight = $this->normalize_int_strict($rawWeight);
                if ($weight === null || $weight < 0 || $weight > 1000000) {
                    $invalid++;
                    continue;
                }
                update_post_meta($productId, self::META_UNIT_WEIGHT, $weight);
            }
        }

        if ($updated === 0 && $invalid === 0) {
            $this->redirect_with_notice('bressol-cost-defaults', 'empty', [
                'q' => $query,
            ]);
        }

        if ($invalid > 0) {
            $this->redirect_with_notice('bressol-cost-defaults', 'partial', [
                'q' => $query,
                'invalid' => $invalid,
            ]);
        }

        $this->redirect_with_notice('bressol-cost-defaults', 'updated', [
            'q' => $query,
        ]);
    }

    public function handle_transport_snapshot_create(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_transport_notice('forbidden');
        }

        check_admin_referer('bressol_transport_snapshot_create');

        $transferId = isset($_POST['transfer_id']) ? absint($_POST['transfer_id']) : 0;
        $totalCost = $this->normalize_int_strict(isset($_POST['total_cost_cents']) ? wp_unslash($_POST['total_cost_cents']) : null);
        $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';

        if ($transferId <= 0 || $totalCost === null || $totalCost < 0) {
            $this->redirect_with_transport_notice('invalid');
        }

        $transfer = (new TransferRepository())->get_transfer($transferId);
        if (!$transfer || ($transfer['status'] ?? '') !== 'received') {
            $this->redirect_with_transport_notice('invalid_transfer');
        }

        try {
            (new TransportAllocator())->create_snapshot(
                $transferId,
                $totalCost,
                get_current_user_id() ?: null,
                $note !== '' ? $note : null
            );
        } catch (\Throwable $exception) {
            $this->redirect_with_transport_notice('failed');
        }

        $this->redirect_with_transport_notice('created');
    }

    public function handle_transport_snapshot_recalc(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_transport_notice('forbidden');
        }

        check_admin_referer('bressol_transport_snapshot_recalc');

        $snapshotId = isset($_POST['snapshot_id']) ? absint($_POST['snapshot_id']) : 0;
        if ($snapshotId <= 0) {
            $this->redirect_with_transport_notice('invalid');
        }

        try {
            (new TransportAllocator())->recalculate_allocations($snapshotId);
        } catch (\Throwable $exception) {
            $this->redirect_with_transport_notice('failed');
        }

        $this->redirect_with_transport_notice('recalc_ok', $snapshotId);
    }

    public function handle_transport_snapshot_close(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_transport_notice('forbidden');
        }

        check_admin_referer('bressol_transport_snapshot_close');

        $snapshotId = isset($_POST['snapshot_id']) ? absint($_POST['snapshot_id']) : 0;
        if ($snapshotId <= 0) {
            $this->redirect_with_transport_notice('invalid');
        }

        try {
            (new TransportAllocator())->close_snapshot($snapshotId, get_current_user_id() ?: null);
        } catch (\Throwable $exception) {
            $this->redirect_with_transport_notice('failed');
        }

        $this->redirect_with_transport_notice('close_ok', $snapshotId);
    }

    private function normalize_int_strict($value, bool $allowNegative = false): ?int
    {
        if ($value === null) {
            return null;
        }
        $raw = is_string($value) ? trim($value) : (string) $value;
        if ($raw === '') {
            return null;
        }
        $pattern = $allowNegative ? '/^-?\d+$/' : '/^\d+$/';
        if (!preg_match($pattern, $raw)) {
            return null;
        }
        return (int) $raw;
    }

    private function redirect_with_notice(string $page, string $notice, array $extra = []): void
    {
        $args = array_merge([
            'page' => $page,
            'cost_defaults_notice' => $notice,
        ], $extra);
        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private function redirect_with_transport_notice(string $notice, ?int $snapshotId = null): void
    {
        $args = [
            'page' => 'bressol-transport',
            'transport_notice' => $notice,
        ];
        if ($snapshotId !== null && $snapshotId > 0) {
            $args['snapshot_id'] = $snapshotId;
        }
        $url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
