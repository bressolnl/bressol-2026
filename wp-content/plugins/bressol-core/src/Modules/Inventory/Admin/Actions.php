<?php
declare(strict_types=1);

namespace Bressol\Modules\Inventory\Admin;

use Bressol\Modules\Inventory\Lots\Repositories\LotMoveRepository;
use Bressol\Modules\Inventory\Lots\Repositories\LotRepository;
use Bressol\Modules\Inventory\Services\ExpiryAlertsService;
use Bressol\Modules\Inventory\Transfers\Repositories\TransferLineRepository;
use Bressol\Modules\Inventory\Transfers\Repositories\TransferRepository;
use Bressol\Modules\Inventory\Transfers\Services\TransferService;

if (!defined('ABSPATH')) {
    exit;
}

final class Actions
{
    private const META_UNIT_COST = '_bressol_default_unit_cogs_cents';
    private const META_UNIT_WEIGHT = '_bressol_unit_weight_grams';
    private const META_CLEARANCE = '_bressol_clearance';
    private const META_CLEARANCE_LOT_ID = '_bressol_clearance_lot_id';
    private LotRepository $lotRepository;
    private LotMoveRepository $lotMoveRepository;
    private TransferRepository $transferRepository;
    private TransferLineRepository $lineRepository;

    public function __construct(
        ?LotRepository $lotRepository = null,
        ?LotMoveRepository $lotMoveRepository = null,
        ?TransferRepository $transferRepository = null,
        ?TransferLineRepository $lineRepository = null
    ) {
        $this->lotRepository = $lotRepository ?? new LotRepository();
        $this->lotMoveRepository = $lotMoveRepository ?? new LotMoveRepository();
        $this->transferRepository = $transferRepository ?? new TransferRepository();
        $this->lineRepository = $lineRepository ?? new TransferLineRepository();
    }

    public function register(): void
    {
        add_action('admin_post_bressol_lot_create_es', [$this, 'handle_lot_create_es']);
        add_action('admin_post_bressol_lot_adjust', [$this, 'handle_lot_adjust']);
        add_action('admin_post_bressol_transfer_create', [$this, 'handle_transfer_create']);
        add_action('admin_post_bressol_transfer_save_lines', [$this, 'handle_transfer_save_lines']);
        add_action('admin_post_bressol_transfer_ship', [$this, 'handle_transfer_ship']);
        add_action('admin_post_bressol_transfer_receive', [$this, 'handle_transfer_receive']);
        add_action('admin_post_bressol_mark_clearance', [$this, 'handle_mark_clearance']);
        add_action('admin_post_bressol_refresh_expiry_alerts', [$this, 'handle_refresh_expiry_alerts']);
    }

    public function handle_lot_create_es(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('bressol-lots', 'forbidden');
        }

        check_admin_referer('bressol_lot_create_es');

        $productId = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $qty = $this->normalize_int_strict(isset($_POST['qty']) ? wp_unslash($_POST['qty']) : null);
        $expiry = isset($_POST['expiry_date']) ? sanitize_text_field(wp_unslash($_POST['expiry_date'])) : '';
        $unitCogs = $this->normalize_int_strict(isset($_POST['unit_cogs_cents']) ? wp_unslash($_POST['unit_cogs_cents']) : null);
        $unitWeight = $this->normalize_int_strict(isset($_POST['unit_weight_grams']) ? wp_unslash($_POST['unit_weight_grams']) : null);
        $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';

        if ($productId <= 0 || $qty === null || $qty < 1 || $qty > 100000) {
            $this->redirect_with_notice('bressol-lots', 'invalid_input');
        }

        $product = get_post($productId);
        if (!$product || $product->post_type !== 'product') {
            $this->redirect_with_notice('bressol-lots', 'invalid_product');
        }

        $expiryDate = $this->normalize_date($expiry);
        if ($expiry !== '' && $expiryDate === null) {
            $this->redirect_with_notice('bressol-lots', 'invalid_expiry', [
                'prefill_product_id' => $productId,
            ]);
        }

        if ($unitCogs === null || $unitCogs <= 0 || $unitCogs > 10000000) {
            $defaultCost = get_post_meta($productId, self::META_UNIT_COST, true);
            $defaultCost = is_numeric($defaultCost) ? (int) $defaultCost : 0;
            if ($defaultCost <= 0 || $defaultCost > 10000000) {
                $this->redirect_with_notice('bressol-lots', 'default_cost_missing', [
                    'prefill_product_id' => $productId,
                ]);
            }
            $unitCogs = $defaultCost;
        }

        if ($unitWeight === null || $unitWeight < 0 || $unitWeight > 1000000) {
            $defaultWeight = get_post_meta($productId, self::META_UNIT_WEIGHT, true);
            $defaultWeight = is_numeric($defaultWeight) ? (int) $defaultWeight : 0;
            $unitWeight = ($defaultWeight >= 0 && $defaultWeight <= 1000000) ? $defaultWeight : 0;
        }

        $lotId = $this->lotRepository->create_lot([
            'product_id' => $productId,
            'location' => 'ES',
            'qty_on_hand' => $qty,
            'expiry_date' => $expiryDate,
            'unit_cogs_cents' => $unitCogs,
            'unit_weight_grams' => $unitWeight,
            'source' => 'manual',
        ]);

        if ($lotId <= 0) {
            $this->redirect_with_notice('bressol-lots', 'create_failed', [
                'prefill_product_id' => $productId,
            ]);
        }

        $notePayload = [
            'source' => 'admin_lot_create',
            'product_id' => $productId,
            'note' => $this->truncate_note($note),
        ];
        $noteJson = wp_json_encode($notePayload);

        $moveId = $this->lotMoveRepository->add_move($lotId, 'receipt', $qty, 'manual', 'lot_create_es', $noteJson ?: null);

        if ($moveId <= 0) {
            $this->redirect_with_notice('bressol-lots', 'create_move_failed', [
                'prefill_product_id' => $productId,
            ]);
        }

        $this->redirect_with_notice('bressol-lots', 'created', [
            'prefill_product_id' => $productId,
        ]);
    }

    public function handle_lot_adjust(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('bressol-lots', 'forbidden');
        }

        check_admin_referer('bressol_lot_adjust');

        $lotId = isset($_POST['lot_id']) ? absint($_POST['lot_id']) : 0;
        $delta = $this->normalize_int_strict(isset($_POST['delta']) ? wp_unslash($_POST['delta']) : null, true);
        $reason = isset($_POST['reason']) ? sanitize_key(wp_unslash($_POST['reason'])) : '';
        $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';

        $allowedReasons = ['count', 'damage', 'correction', 'other'];
        if (!in_array($reason, $allowedReasons, true)) {
            $reason = 'other';
        }

        if ($lotId <= 0 || $delta === null || $delta === 0) {
            $this->redirect_with_notice('bressol-lots', 'invalid_adjust');
        }

        $lot = $this->lotRepository->get_lot_by_id($lotId);
        if (!$lot) {
            $this->redirect_with_notice('bressol-lots', 'invalid_lot');
        }

        $ok = $this->lotRepository->increment_qty($lotId, $delta);
        if (!$ok) {
            $this->redirect_with_notice('bressol-lots', 'adjust_failed', [
                'prefill_lot_id' => $lotId,
            ]);
        }

        $notePayload = [
            'source' => 'admin_lot_adjust',
            'delta' => $delta,
            'reason' => $reason,
            'note' => $this->truncate_note($note),
        ];
        $noteJson = wp_json_encode($notePayload);

        $moveId = $this->lotMoveRepository->add_move(
            $lotId,
            'adjust',
            abs($delta),
            'manual',
            'lot_adjust',
            $noteJson ?: null
        );

        if ($moveId <= 0) {
            $this->redirect_with_notice('bressol-lots', 'adjust_move_failed', [
                'prefill_lot_id' => $lotId,
            ]);
        }

        $this->redirect_with_notice('bressol-lots', 'adjusted', [
            'prefill_lot_id' => $lotId,
        ]);
    }

    public function handle_transfer_create(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('bressol-transfers', 'forbidden', [], 'transfers_notice');
        }

        check_admin_referer('bressol_transfer_create');

        $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
        $lines = $this->parse_transfer_lines($_POST);
        if ($lines === []) {
            $this->redirect_with_notice('bressol-transfers', 'invalid_lines', [], 'transfers_notice');
        }

        $service = new TransferService(
            $this->transferRepository,
            $this->lineRepository
        );
        try {
            $transferId = $service->create_transfer($lines, get_current_user_id() ?: null, $note !== '' ? $note : null);
        } catch (\Throwable $exception) {
            $transferId = 0;
        }

        if ($transferId <= 0) {
            $this->redirect_with_notice('bressol-transfers', 'invalid', [], 'transfers_notice');
        }

        $this->redirect_with_notice('bressol-transfers', 'created', [
            'transfer_id' => $transferId,
        ], 'transfers_notice');
    }

    public function handle_transfer_save_lines(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('bressol-transfers', 'forbidden', [], 'transfers_notice');
        }

        check_admin_referer('bressol_transfer_save_lines');

        $transferId = isset($_POST['transfer_id']) ? absint($_POST['transfer_id']) : 0;
        if ($transferId <= 0) {
            $this->redirect_with_notice('bressol-transfers', 'invalid', [], 'transfers_notice');
        }

        $transfer = $this->transferRepository->get_transfer($transferId);
        if (!$transfer || ($transfer['status'] ?? '') !== 'draft') {
            $this->redirect_with_notice('bressol-transfers', 'not_draft', [
                'transfer_id' => $transferId,
            ], 'transfers_notice');
        }

        $lines = $this->parse_transfer_lines($_POST);
        if ($lines === []) {
            $this->redirect_with_notice('bressol-transfers', 'invalid_lines', [
                'transfer_id' => $transferId,
            ], 'transfers_notice');
        }

        $this->lineRepository->delete_by_transfer($transferId);
        foreach ($lines as $line) {
            $lineId = $this->lineRepository->add_line(
                $transferId,
                (int) $line['product_id'],
                (int) $line['qty_units'],
                $line['expiry_date'] ?? null,
                $line['unit_cogs_cents'] ?? null,
                $line['unit_weight_override_grams'] ?? null,
                $line['line_weight_total_grams'] ?? null
            );

            if ($lineId <= 0) {
                $this->redirect_with_notice('bressol-transfers', 'lines_save_failed', [
                    'transfer_id' => $transferId,
                ], 'transfers_notice');
            }
        }

        $this->redirect_with_notice('bressol-transfers', 'lines_saved', [
            'transfer_id' => $transferId,
        ], 'transfers_notice');
    }

    public function handle_transfer_ship(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('bressol-transfers', 'forbidden', [], 'transfers_notice');
        }

        check_admin_referer('bressol_transfer_ship');

        $transferId = isset($_POST['transfer_id']) ? absint($_POST['transfer_id']) : 0;
        if ($transferId <= 0) {
            $this->redirect_with_notice('bressol-transfers', 'invalid', [], 'transfers_notice');
        }

        try {
            (new TransferService($this->transferRepository, $this->lineRepository))->ship($transferId);
        } catch (\Throwable $exception) {
            $this->redirect_with_notice('bressol-transfers', 'ship_failed', [
                'transfer_id' => $transferId,
            ], 'transfers_notice');
        }

        $this->redirect_with_notice('bressol-transfers', 'ship_ok', [
            'transfer_id' => $transferId,
        ], 'transfers_notice');
    }

    public function handle_transfer_receive(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('bressol-transfers', 'forbidden', [], 'transfers_notice');
        }

        check_admin_referer('bressol_transfer_receive');

        $transferId = isset($_POST['transfer_id']) ? absint($_POST['transfer_id']) : 0;
        if ($transferId <= 0) {
            $this->redirect_with_notice('bressol-transfers', 'invalid', [], 'transfers_notice');
        }

        try {
            (new TransferService($this->transferRepository, $this->lineRepository))->receive($transferId);
        } catch (\Throwable $exception) {
            $this->redirect_with_notice('bressol-transfers', 'receive_failed', [
                'transfer_id' => $transferId,
            ], 'transfers_notice');
        }

        $this->redirect_with_notice('bressol-transfers', 'receive_ok', [
            'transfer_id' => $transferId,
        ], 'transfers_notice');
    }

    public function handle_mark_clearance(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('bressol-expiry-alerts', 'forbidden', [], 'expiry_notice');
        }

        check_admin_referer('bressol_mark_clearance');

        $productId = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $lotId = isset($_POST['lot_id']) ? absint($_POST['lot_id']) : 0;
        if ($productId <= 0) {
            $this->redirect_with_notice('bressol-expiry-alerts', 'invalid', [], 'expiry_notice');
        }

        $product = get_post($productId);
        if (!$product || $product->post_type !== 'product') {
            $this->redirect_with_notice('bressol-expiry-alerts', 'invalid_product', [], 'expiry_notice');
        }

        update_post_meta($productId, self::META_CLEARANCE, current_time('mysql'));
        if ($lotId > 0) {
            update_post_meta($productId, self::META_CLEARANCE_LOT_ID, (string) $lotId);
        }

        $this->redirect_with_notice('bressol-expiry-alerts', 'clearance_ok', [], 'expiry_notice');
    }

    public function handle_refresh_expiry_alerts(): void
    {
        if (!current_user_can('manage_options')) {
            $this->redirect_with_notice('bressol-expiry-alerts', 'forbidden', [], 'expiry_notice');
        }

        check_admin_referer('bressol_refresh_expiry_alerts');

        (new ExpiryAlertsService())->refresh_cache();

        $this->redirect_with_notice('bressol-expiry-alerts', 'refreshed', [], 'expiry_notice');
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

    private function normalize_date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date && $date->format('Y-m-d') === $value) {
            return $value;
        }
        return null;
    }

    private function truncate_note(string $note): string
    {
        $note = trim($note);
        if ($note === '') {
            return '';
        }
        return strlen($note) > 200 ? substr($note, 0, 200) : $note;
    }

    /** @return array<int, array<string, mixed>> */
    private function parse_transfer_lines(array $input): array
    {
        $productIds = isset($input['line_product_id']) ? (array) wp_unslash($input['line_product_id']) : [];
        $qtys = isset($input['line_qty']) ? (array) wp_unslash($input['line_qty']) : [];
        $expiries = isset($input['line_expiry']) ? (array) wp_unslash($input['line_expiry']) : [];
        $unitCogs = isset($input['line_unit_cogs']) ? (array) wp_unslash($input['line_unit_cogs']) : [];
        $unitWeights = isset($input['line_unit_weight']) ? (array) wp_unslash($input['line_unit_weight']) : [];
        $lineWeights = isset($input['line_weight_total']) ? (array) wp_unslash($input['line_weight_total']) : [];

        $count = max(
            count($productIds),
            count($qtys),
            count($expiries),
            count($unitCogs),
            count($unitWeights),
            count($lineWeights)
        );

        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $productId = $this->normalize_int_strict($productIds[$i] ?? null);
            $qty = $this->normalize_int_strict($qtys[$i] ?? null);
            if ($productId === null || $productId <= 0 || $qty === null || $qty < 1 || $qty > 100000) {
                continue;
            }

            $expiryRaw = $expiries[$i] ?? '';
            $expiry = $expiryRaw !== '' ? $this->normalize_date((string) $expiryRaw) : null;

            $cogs = $this->normalize_int_strict($unitCogs[$i] ?? null);
            $cogs = $cogs !== null && $cogs > 0 && $cogs <= 10000000 ? $cogs : null;

            $weightOverride = $this->normalize_int_strict($unitWeights[$i] ?? null);
            $weightOverride = $weightOverride !== null && $weightOverride >= 0 && $weightOverride <= 1000000
                ? $weightOverride
                : null;

            $lineWeightTotal = $this->normalize_int_strict($lineWeights[$i] ?? null);
            $lineWeightTotal = $lineWeightTotal !== null && $lineWeightTotal >= 0 && $lineWeightTotal <= 1000000
                ? $lineWeightTotal
                : null;

            $lines[] = [
                'product_id' => $productId,
                'qty_units' => $qty,
                'expiry_date' => $expiry,
                'unit_cogs_cents' => $cogs,
                'unit_weight_override_grams' => $weightOverride,
                'line_weight_total_grams' => $lineWeightTotal,
            ];
        }

        return $lines;
    }


    private function redirect_with_notice(string $page, string $notice, array $extra = [], string $noticeKey = 'lots_notice'): void
    {
        $url = wp_get_referer();
        if (!$url || strpos($url, 'admin.php') === false) {
            $url = admin_url('admin.php');
        }
        $url = remove_query_arg(['lots_notice', 'cost_defaults_notice', 'transfers_notice', 'expiry_notice'], $url);
        $args = array_merge([
            'page' => $page,
            $noticeKey => $notice,
        ], $extra);
        $url = add_query_arg($args, $url);
        wp_safe_redirect($url);
        exit;
    }
}
