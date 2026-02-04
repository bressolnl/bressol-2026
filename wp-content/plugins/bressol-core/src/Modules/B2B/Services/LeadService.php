<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Services;

use Bressol\Modules\B2B\Repositories\LeadEventsRepository;
use Bressol\Modules\B2B\Repositories\LeadRepository;
use Bressol\Modules\B2B\Support\Masking;
use Bressol\Modules\Crm\Services\CustomerService;

if (!defined('ABSPATH')) {
    exit;
}

final class LeadService
{
    private LeadRepository $leads;
    private LeadEventsRepository $events;
    private TaskService $tasks;
    private Settings $settings;

    public function __construct(
        ?LeadRepository $leads = null,
        ?LeadEventsRepository $events = null,
        ?TaskService $tasks = null,
        ?Settings $settings = null
    ) {
        $this->leads = $leads ?? new LeadRepository();
        $this->events = $events ?? new LeadEventsRepository();
        $this->tasks = $tasks ?? new TaskService();
        $this->settings = $settings ?? new Settings();
    }

    /** @param array<string, mixed> $payload
     *  @return array{lead_id:int,is_new:bool,errors:array<int,string>}
     */
    public function create_or_update(array $payload, int $actorId, string $source = 'admin'): array
    {
        $errors = [];
        $email = isset($payload['email']) ? sanitize_email((string) $payload['email']) : '';
        if ($email === '' || !is_email($email)) {
            $errors[] = 'Email inválido.';
        }

        if ($errors !== []) {
            return ['lead_id' => 0, 'is_new' => false, 'errors' => $errors];
        }

        $emailLower = strtolower($email);
        $requestedId = isset($payload['lead_id']) ? (int) $payload['lead_id'] : 0;
        $existing = $requestedId > 0 ? $this->leads->find_by_id($requestedId) : null;
        $byEmail = $this->leads->find_by_email_lower($emailLower);
        if ($existing && $byEmail && (int) $byEmail['id'] !== (int) $existing['id']) {
            return ['lead_id' => 0, 'is_new' => false, 'errors' => ['Email ya existe.']];
        }
        if (!$existing) {
            $existing = $byEmail;
        }
        $now = current_time('mysql');

        $data = $existing ?? [
            'created_at' => $now,
            'lead_score' => 0,
            'last_activity_at' => $now,
        ];

        $data['updated_at'] = $now;
        $data['email'] = $email;
        $data['email_lower'] = $emailLower;
        $data['company_name'] = isset($payload['company_name']) ? sanitize_text_field((string) $payload['company_name']) : ($data['company_name'] ?? null);
        $data['contact_name'] = isset($payload['contact_name']) ? sanitize_text_field((string) $payload['contact_name']) : ($data['contact_name'] ?? null);
        $data['city'] = isset($payload['city']) ? sanitize_text_field((string) $payload['city']) : ($data['city'] ?? null);
        $data['phone'] = isset($payload['phone']) ? sanitize_text_field((string) $payload['phone']) : ($data['phone'] ?? null);
        $data['business_type'] = isset($payload['business_type']) ? $this->sanitize_business_type((string) $payload['business_type']) : ($data['business_type'] ?? null);
        $data['tier'] = isset($payload['tier']) ? $this->sanitize_tier((string) $payload['tier']) : ($data['tier'] ?? $this->default_tier());
        $data['status'] = isset($payload['status']) ? $this->sanitize_status((string) $payload['status']) : ($data['status'] ?? 'NEEDS_CONSENT');
        $data['contact_basis'] = isset($payload['contact_basis']) ? $this->sanitize_contact_basis((string) $payload['contact_basis']) : ($data['contact_basis'] ?? 'no_consent');
        $data['source'] = isset($payload['source']) ? $this->sanitize_source((string) $payload['source']) : ($data['source'] ?? $source);
        $data['source_ref_event_id'] = isset($payload['source_ref_event_id']) ? (int) $payload['source_ref_event_id'] : ($data['source_ref_event_id'] ?? null);
        $data['interests_json'] = array_key_exists('interests_json', $payload) ? $this->normalize_interests($payload['interests_json']) : ($data['interests_json'] ?? null);
        $data['owner_user_id'] = isset($payload['owner_user_id']) && (int) $payload['owner_user_id'] > 0
            ? (int) $payload['owner_user_id']
            : (int) ($data['owner_user_id'] ?? $this->settings->get_default_owner_user_id());
        $newStage = array_key_exists('sales_stage', $payload)
            ? $this->sanitize_sales_stage((string) $payload['sales_stage'])
            : ($data['sales_stage'] ?? '');
        $prevStage = (string) ($data['sales_stage'] ?? '');
        if ($newStage === '') {
            $newStage = 'new';
        }
        $data['sales_stage'] = $newStage;
        $data['next_followup_at'] = array_key_exists('next_followup_at', $payload)
            ? $this->normalize_datetime((string) $payload['next_followup_at'])
            : ($data['next_followup_at'] ?? null);

        if (($data['status'] ?? '') === '') {
            $data['status'] = $this->status_from_contact_basis((string) ($data['contact_basis'] ?? 'no_consent'));
        }

        if ($newStage !== '' && $newStage !== $prevStage) {
            $data['last_activity_at'] = $now;
        }

        $tokenData = $this->ensure_token($data);
        $data = array_merge($data, $tokenData);

        $leadId = 0;
        $isNew = $existing === null;
        if ($isNew) {
            $leadId = $this->leads->insert($data);
            if ($leadId === 0) {
                $existing = $this->leads->find_by_email_lower($emailLower);
                if ($existing) {
                    $leadId = (int) ($existing['id'] ?? 0);
                    if ($leadId > 0) {
                        $this->leads->update($leadId, $data);
                        $isNew = false;
                    }
                }
            }
        } else {
            $leadId = (int) ($existing['id'] ?? 0);
            if ($leadId > 0) {
                $this->leads->update($leadId, $data);
            }
        }

        if ($leadId > 0) {
            $context = [
                'email' => Masking::mask_email($email),
                'source' => $data['source'] ?? $source,
            ];
            $this->events->insert_event($leadId, $isNew ? 'lead_created' : 'lead_updated', $context);
        }

        return ['lead_id' => $leadId, 'is_new' => $isNew, 'errors' => []];
    }

    /** @param array<string, mixed> $payload
     *  @return array{lead_id:int,is_new:bool,errors:array<int,string>}
     */
    public function create_or_update_lead_from_pos(array $payload): array
    {
        $payload['source'] = 'pos';
        if (isset($payload['event_id'])) {
            $payload['source_ref_event_id'] = (int) $payload['event_id'];
        }
        $payload['status'] = $payload['status'] ?? 'NEEDS_CONSENT';
        $payload['contact_basis'] = $payload['contact_basis'] ?? 'no_consent';

        return $this->create_or_update($payload, 0, 'pos');
    }

    public function apply_consent(int $leadId): bool
    {
        return $this->apply_consent_with_task($leadId, 'call_after_consent', 'Bel deze lead (nieuw consent)', 48, 'b2b_consent');
    }

    public function apply_consent_with_task(
        int $leadId,
        string $taskType,
        string $taskNote,
        int $dueHours,
        string $consentSource = 'b2b'
    ): bool
    {
        if ($leadId <= 0) {
            return false;
        }

        $lead = $this->leads->find_by_id($leadId);
        if (!$lead) {
            return false;
        }

        $alreadyConsented = !empty($lead['consented_at']);

        if (!$alreadyConsented) {
            $now = current_time('mysql');
            $updated = $this->leads->update($leadId, [
                'contact_basis' => 'consent_explicit',
                'status' => 'CONSENTED',
                'consented_at' => $now,
                'updated_at' => $now,
                'last_activity_at' => $now,
            ]);
            if (!$updated) {
                return false;
            }
            $this->leads->increment_score($leadId, 5);
        }

        if (!$this->events->has_event($leadId, 'consent_given')) {
            $this->events->insert_event($leadId, 'consent_given', []);
        }

        $lead = $this->leads->find_by_id($leadId) ?? $lead;
        $synced = $this->sync_marketing_consent($lead, $consentSource);

        $this->tasks->create_auto_task($leadId, $taskType, $taskNote, $dueHours);

        if ($synced) {
            $espRecentlyQueued = $this->events->has_recent_event($leadId, 'esp_email_queued', 86400);
            if (!$espRecentlyQueued) {
                $espSent = (new EspService())->enqueue_b2b_catalog_email($lead, (string) ($lead['consent_token'] ?? ''));
                if ($espSent) {
                    $this->events->insert_event($leadId, 'esp_email_queued', []);
                }
            }
        }

        return true;
    }

    /** @param array<string, mixed> $payload
     *  @return array{lead_id:int,is_new:bool,errors:array<int,string>}
     */
    public function register_signup(array $payload): array
    {
        $payload['source'] = 'signup';
        $payload['contact_basis'] = 'consent_explicit';
        $payload['status'] = 'CONSENTED';
        $payload['consented_at'] = current_time('mysql');

        $result = $this->create_or_update($payload, 0, 'signup');
        if ($result['lead_id'] <= 0) {
            return $result;
        }

        $leadId = $result['lead_id'];
        $lead = $this->leads->find_by_id($leadId);
        if (!$lead) {
            return $result;
        }

        $this->events->insert_event($leadId, 'signup_submitted', [
            'source' => 'signup',
        ]);

        $this->apply_consent_with_task($leadId, 'follow_up', 'Follow-up', 48, 'b2b_signup');
        return $result;
    }

    /** @param array<string, mixed> $lead */
    public function sync_marketing_consent(array $lead, string $source): bool
    {
        if (!class_exists(CustomerService::class)) {
            return false;
        }
        $email = (string) ($lead['email'] ?? '');
        if ($email === '' || !is_email($email)) {
            return false;
        }

        $service = new CustomerService();
        $customerId = $service->save_customer([
            'email' => $email,
            'customer_type' => 'b2b',
            'status' => 'active',
            'can_receive_marketing' => 1,
            'can_be_profiled' => 1,
            'loyalty_enabled' => 0,
        ]);
        if ($customerId <= 0) {
            return false;
        }

        $grantedAt = (string) ($lead['consented_at'] ?? '');
        if ($grantedAt === '') {
            $grantedAt = current_time('mysql');
        }

        global $wpdb;
        $metaTable = $wpdb->prefix . 'bressol_crm_customer_meta';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $metaTable));
        if ($exists !== $metaTable) {
            return true;
        }

        $this->upsert_customer_meta($customerId, 'marketing_consent_basis', 'consent_explicit');
        $this->upsert_customer_meta($customerId, 'marketing_consent_source', $source);
        $this->upsert_customer_meta($customerId, 'marketing_consent_granted_at', $grantedAt);

        return true;
    }

    /** @param array<string, mixed> $payload */
    public function complete_profile(int $leadId, array $payload, bool $sendEmail = true): bool
    {
        if ($leadId <= 0) {
            return false;
        }

        $lead = $this->leads->find_by_id($leadId);
        if (!$lead) {
            return false;
        }

        $beforeComplete = $this->is_profile_complete($lead);
        $data = [
            'company_name' => isset($payload['company_name']) ? sanitize_text_field((string) $payload['company_name']) : ($lead['company_name'] ?? null),
            'city' => isset($payload['city']) ? sanitize_text_field((string) $payload['city']) : ($lead['city'] ?? null),
            'phone' => isset($payload['phone']) ? sanitize_text_field((string) $payload['phone']) : ($lead['phone'] ?? null),
            'updated_at' => current_time('mysql'),
            'last_activity_at' => current_time('mysql'),
        ];

        $updated = $this->leads->update($leadId, $data);
        if (!$updated) {
            return false;
        }

        $lead = $this->leads->find_by_id($leadId);
        if (!$lead) {
            return false;
        }

        $afterComplete = $this->is_profile_complete($lead);
        if (!$beforeComplete && $afterComplete) {
            $this->events->insert_event($leadId, 'profile_completed', []);
            $this->tasks->create_auto_task($leadId, 'hot_lead_call', 'HOT lead - call', 24);

            if ($sendEmail) {
                $espRecentlyQueued = $this->events->has_recent_event($leadId, 'profile_email_queued', 86400);
                if (!$espRecentlyQueued) {
                    $espSent = (new EspService())->enqueue_b2b_profile_confirmation_email($lead);
                    if ($espSent) {
                        $this->events->insert_event($leadId, 'profile_email_queued', []);
                    }
                }
            }
        }

        return true;
    }

    /** @param array<string, mixed> $lead */
    public function is_profile_complete(array $lead): bool
    {
        $company = trim((string) ($lead['company_name'] ?? ''));
        $city = trim((string) ($lead['city'] ?? ''));
        return $company !== '' && $city !== '';
    }


    public function regenerate_token(int $leadId): bool
    {
        if ($leadId <= 0) {
            return false;
        }

        $lead = $this->leads->find_by_id($leadId);
        if (!$lead) {
            return false;
        }

        $ttlDays = $this->settings->get_token_ttl_days();
        $nowTs = current_time('timestamp');
        $tokenData = [
            'consent_token' => $this->generate_token(),
            'consent_token_created_at' => current_time('mysql'),
            'consent_token_expires_at' => date('Y-m-d H:i:s', $nowTs + ($ttlDays * 86400)),
            'updated_at' => current_time('mysql'),
        ];

        return $this->leads->update($leadId, $tokenData);
    }

    public function register_catalog_click(int $leadId): void
    {
        $this->register_click($leadId, 'catalog_clicked', 10);
    }

    public function register_pricelist_click(int $leadId): void
    {
        $this->register_click($leadId, 'pricelist_clicked', 20);
        $this->tasks->create_auto_task($leadId, 'pricelist_hot', 'Hot lead: prijslijst bekeken', 24);
    }

    private function register_click(int $leadId, string $type, int $score): void
    {
        if ($leadId <= 0) {
            return;
        }

        $recentSeconds = 24 * 3600;
        $already = $this->events->has_recent_event($leadId, $type, $recentSeconds);
        $recentEvent = $this->events->has_recent_event_minutes($leadId, $type, 5);
        if (!$recentEvent) {
            $this->events->insert_event($leadId, $type, []);
        }
        if (!$already) {
            $this->leads->increment_score($leadId, $score);
        }
        $this->leads->update($leadId, [
            'last_activity_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
    }

    /** @param array<string, mixed> $lead
     *  @return array<string, string>
     */
    private function ensure_token(array $lead): array
    {
        $token = (string) ($lead['consent_token'] ?? '');
        $expiresAt = (string) ($lead['consent_token_expires_at'] ?? '');
        $nowTs = current_time('timestamp');
        $expired = $expiresAt !== '' ? strtotime($expiresAt) < $nowTs : true;

        if ($token !== '' && !$expired) {
            return [];
        }

        $ttlDays = $this->settings->get_token_ttl_days();
        $createdAt = current_time('mysql');
        $expiresAt = date('Y-m-d H:i:s', $nowTs + ($ttlDays * 86400));

        return [
            'consent_token' => $this->generate_token(),
            'consent_token_created_at' => $createdAt,
            'consent_token_expires_at' => $expiresAt,
        ];
    }

    private function generate_token(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return wp_generate_password(64, false, false);
        }
    }

    private function sanitize_status(string $status): string
    {
        $status = strtoupper(sanitize_key($status));
        $allowed = ['NEW', 'NEEDS_CONSENT', 'CONSENTED', 'ENGAGED', 'SQL', 'WON', 'LOST'];
        return in_array($status, $allowed, true) ? $status : 'NEEDS_CONSENT';
    }

    private function sanitize_contact_basis(string $basis): string
    {
        $basis = sanitize_key($basis);
        $allowed = ['consent_explicit', 'relationship_1to1_followup', 'no_consent'];
        return in_array($basis, $allowed, true) ? $basis : 'no_consent';
    }

    private function sanitize_tier(string $tier): string
    {
        $tier = sanitize_key($tier);
        $tiers = $this->settings->get_tier_options();
        return in_array($tier, $tiers, true) ? $tier : $this->default_tier();
    }

    private function sanitize_business_type(string $type): string
    {
        $type = sanitize_key($type);
        if ($type === '') {
            return '';
        }
        $allowed = ['gourmet', 'horeca', 'corporate', 'other'];
        return in_array($type, $allowed, true) ? $type : 'other';
    }

    private function sanitize_sales_stage(string $stage): string
    {
        $stage = sanitize_key($stage);
        $allowed = ['new', 'contacted', 'sample_sent', 'negotiation', 'won', 'lost'];
        return in_array($stage, $allowed, true) ? $stage : 'new';
    }

    private function upsert_customer_meta(int $customerId, string $key, string $value): void
    {
        if ($customerId <= 0 || $key === '') {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bressol_crm_customer_meta';
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE customer_id = %d AND meta_key = %s LIMIT 1",
                $customerId,
                $key
            )
        );
        $payload = [
            'customer_id' => $customerId,
            'meta_key' => $key,
            'meta_value' => sanitize_text_field($value),
        ];
        if ($existing) {
            $wpdb->update($table, ['meta_value' => $payload['meta_value']], ['id' => (int) $existing], ['%s'], ['%d']);
            return;
        }
        $wpdb->insert($table, $payload, ['%d', '%s', '%s']);
    }

    private function sanitize_source(string $source): string
    {
        $source = sanitize_key($source);
        return $source !== '' ? $source : 'admin';
    }

    private function default_tier(): string
    {
        $tiers = $this->settings->get_tier_options();
        return $tiers[0] ?? 'other';
    }

    private function normalize_datetime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return null;
        }
        return $value;
    }

    private function status_from_contact_basis(string $basis): string
    {
        if ($basis === 'consent_explicit') {
            return 'CONSENTED';
        }
        if ($basis === 'relationship_1to1_followup') {
            return 'NEW';
        }
        return 'NEEDS_CONSENT';
    }

    /** @param mixed $interests */
    private function normalize_interests($interests): ?string
    {
        if ($interests === null || $interests === '') {
            return null;
        }
        if (is_array($interests)) {
            return wp_json_encode($interests);
        }
        return sanitize_textarea_field((string) $interests);
    }

    /** @param array<string, mixed> $lead
     *  @return array{temperature:string,reason:string,stale:bool}
     */
    public function compute_temperature(array $lead): array
    {
        $lastEvent = sanitize_key((string) ($lead['last_event_type'] ?? ''));
        $score = isset($lead['lead_score']) ? (int) $lead['lead_score'] : 0;
        $lastActivity = (string) ($lead['last_activity_at'] ?? '');

        $stale = false;
        if ($lastActivity !== '') {
            $cutoff = current_time('timestamp') - (14 * 86400);
            $lastTs = strtotime($lastActivity);
            $stale = $lastTs !== false && $lastTs <= $cutoff;
        }

        $hotEvents = ['pricelist_clicked', 'profile_completed'];
        if (in_array($lastEvent, $hotEvents, true)) {
            return ['temperature' => 'HOT', 'reason' => 'event:' . $lastEvent, 'stale' => $stale];
        }
        if ($score >= 35) {
            return ['temperature' => 'HOT', 'reason' => 'score>=35', 'stale' => $stale];
        }
        if ($lastEvent === 'catalog_clicked') {
            return ['temperature' => 'WARM', 'reason' => 'event:catalog_clicked', 'stale' => $stale];
        }
        if ($score >= 15) {
            return ['temperature' => 'WARM', 'reason' => 'score>=15', 'stale' => $stale];
        }

        return ['temperature' => 'COLD', 'reason' => 'default', 'stale' => $stale];
    }

}
