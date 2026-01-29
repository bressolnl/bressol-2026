<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Frontend;

use Bressol\Modules\B2B\Repositories\LeadEventsRepository;
use Bressol\Modules\B2B\Repositories\LeadRepository;
use Bressol\Modules\B2B\Services\LeadService;

if (!defined('ABSPATH')) {
    exit;
}

final class Endpoints
{
    private const TOKEN_REGEX = '/^[a-f0-9]{64}$/';
    private const CONSENT_THROTTLE_SECONDS = 60;
    private const SIGNUP_THROTTLE_SECONDS = 60;
    private const PROFILE_THROTTLE_SECONDS = 60;
    private const GENERIC_ERROR = 'Deze link is ongeldig of verlopen.';

    private LeadRepository $leads;
    private LeadEventsRepository $events;
    private LeadService $leadService;

    public function __construct()
    {
        $this->leads = new LeadRepository();
        $this->events = new LeadEventsRepository();
        $this->leadService = new LeadService();
    }

    public function register_rewrite(): void
    {
        add_rewrite_rule('^b2b/catalog/?$', 'index.php?bressol_b2b=catalog', 'top');
        add_rewrite_rule('^b2b/pricelist/?$', 'index.php?bressol_b2b=pricelist', 'top');
        add_rewrite_rule('^b2b/signup/?$', 'index.php?bressol_b2b=signup', 'top');
    }

    /** @param string[] $vars
     *  @return string[]
     */
    public function register_query_vars(array $vars): array
    {
        $vars[] = 'bressol_b2b';
        return $vars;
    }

    public function handle_request(): void
    {
        $page = get_query_var('bressol_b2b');
        if (!in_array($page, ['catalog', 'pricelist', 'signup'], true)) {
            return;
        }

        if ($page === 'signup') {
            $this->handle_signup_request();
            return;
        }

        $tokenRaw = isset($_GET['token']) ? sanitize_text_field((string) wp_unslash($_GET['token'])) : '';
        $token = $this->normalize_token($tokenRaw);
        if (!$this->is_token_format_valid($token)) {
            $this->render_error(self::GENERIC_ERROR);
            return;
        }

        $lead = $this->leads->find_by_token($token);
        if (!$lead || !$this->is_token_valid($lead) || !$this->tokens_match($token, (string) ($lead['consent_token'] ?? ''))) {
            $this->render_error(self::GENERIC_ERROR);
            return;
        }

        $consented = !empty($lead['consented_at']);

        $consentJustGiven = false;
        if ($this->is_consent_submission()) {
            if (!$this->verify_consent_nonce()) {
                $this->render_error(self::GENERIC_ERROR);
                return;
            }
            if ($this->is_throttled($token)) {
                $this->render_error('Probeer later opnieuw.');
                return;
            }
            $this->touch_throttle($token);
            $agree = !empty($_POST['consent']);
            if ($agree && !$consented) {
                $consentJustGiven = $this->leadService->apply_consent((int) $lead['id']);
                $lead = $this->leads->find_by_id((int) $lead['id']);
                $consented = $consentJustGiven || !empty($lead['consented_at']);
            }
        }

        $download = isset($_GET['download']) ? sanitize_key((string) wp_unslash($_GET['download'])) : '';
        $attemptDownload = $this->is_get_request() && $download === '1';

        $profileJustCompleted = false;
        $profileErrors = [];
        if ($page === 'pricelist' && $consented && $this->is_profile_submission()) {
            if (!$this->verify_profile_nonce()) {
                $this->render_error(self::GENERIC_ERROR);
                return;
            }
            if ($this->is_profile_throttled((int) $lead['id'])) {
                $this->render_error('Probeer later opnieuw.');
                return;
            }
            $this->touch_profile_throttle((int) $lead['id']);
            $payload = $this->get_profile_payload($_POST);
            if ($payload['company_name'] === '' || $payload['city'] === '') {
                $profileErrors[] = 'Vul de verplichte velden in.';
            } else {
                $profileJustCompleted = $this->leadService->complete_profile((int) $lead['id'], $payload, true);
                if (!$profileJustCompleted) {
                    $profileErrors[] = 'Er ging iets mis. Probeer opnieuw.';
                }
                $lead = $this->leads->find_by_id((int) $lead['id']) ?? $lead;
            }
        }

        $shouldPromptProfile = $page === 'pricelist'
            && $consented
            && !$this->leadService->is_profile_complete($lead)
            && ($attemptDownload || $this->events->has_event((int) $lead['id'], 'pricelist_clicked'));

        if ($attemptDownload && $consented && !$shouldPromptProfile) {
            $this->handle_download($page, (int) $lead['id']);
            return;
        }

        if ($attemptDownload && $page === 'pricelist' && $consented && $shouldPromptProfile) {
            $this->leadService->register_pricelist_click((int) $lead['id']);
        }

        $this->render_page(
            $page,
            $token,
            $consented,
            $consentJustGiven,
            (int) $lead['id'],
            $shouldPromptProfile,
            $profileJustCompleted,
            $lead,
            $profileErrors
        );
    }

    private function handle_download(string $page, int $leadId): void
    {
        [$path, $filename, $eventName] = $this->get_pdf_info($page);
        if ($path === '' || !file_exists($path) || !is_readable($path)) {
            error_log('B2B PDF missing: ' . $page);
            $this->render_error('Het bestand is niet beschikbaar.');
            return;
        }

        if ($page === 'catalog') {
            $this->leadService->register_catalog_click($leadId);
        } else {
            $this->leadService->register_pricelist_click($leadId);
        }

        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, no-store');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($path));
        header('X-Bressol-Event: ' . $eventName);
        readfile($path);
        exit;
    }

    /** @param array<string, mixed> $lead */
    private function render_page(
        string $page,
        string $token,
        bool $consented,
        bool $consentJustGiven,
        int $leadId,
        bool $showProfileForm,
        bool $profileJustCompleted,
        array $lead,
        array $profileErrors
    ): void
    {
        status_header(200);
        nocache_headers();

        $title = $page === 'catalog' ? 'B2B Catalogus' : 'B2B Prijslijst';
        $downloadUrl = add_query_arg(['token' => $token, 'download' => '1'], home_url('/b2b/' . $page));
        $eventName = $page === 'catalog' ? 'b2b_catalog_clicked' : 'b2b_pricelist_clicked';

        echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html($title) . '</title></head><body>';
        echo '<div style="max-width:640px;margin:40px auto;font-family:Arial, sans-serif;">';
        echo '<h1>' . esc_html($title) . '</h1>';

        if (!$consented) {
            echo '<p>Om de B2B documenten te ontvangen vragen we je om expliciete toestemming.</p>';
            echo '<form method="post">';
            wp_nonce_field('bressol_b2b_consent');
            echo '<label><input type="checkbox" name="consent" value="1" required> Ik geef toestemming om marketinginformatie van Bressol te ontvangen.</label>';
            echo '<p><button type="submit" name="bressol_b2b_consent_submit">Toestemming geven</button></p>';
            echo '</form>';
        } else {
            echo '<p>Je kunt de documenten hieronder downloaden.</p>';
            echo '<p><a class="b2b-download" data-event="' . esc_attr($eventName) . '" href="' . esc_url($downloadUrl) . '">Download PDF</a></p>';
        }

        if ($consentJustGiven) {
            echo '<p><strong>Bedankt! We hebben je toestemming geregistreerd.</strong></p>';
        }
        if ($profileJustCompleted) {
            echo '<p><strong>Bedankt! We hebben je gegevens bijgewerkt.</strong></p>';
        }

        if ($showProfileForm) {
            echo '<hr style="margin:24px 0;" />';
            echo '<h2>Bedrijfsgegevens aanvullen</h2>';
            echo '<p>Om je aanvraag beter te verwerken vragen we nog een paar gegevens.</p>';
            if ($profileErrors !== []) {
                echo '<div style="color:#b32d2e;">' . esc_html(implode(' ', $profileErrors)) . '</div>';
            }
            echo '<form method="post">';
            wp_nonce_field('bressol_b2b_profile');
            echo '<p><label>Bedrijf *</label><br/><input type="text" name="company_name" required value="'
                . esc_attr((string) ($lead['company_name'] ?? '')) . '" /></p>';
            echo '<p><label>Stad / locatie *</label><br/><input type="text" name="city" required value="'
                . esc_attr((string) ($lead['city'] ?? '')) . '" /></p>';
            echo '<p><label>Telefoon</label><br/><input type="text" name="phone" value="'
                . esc_attr((string) ($lead['phone'] ?? '')) . '" /></p>';
            echo '<p><button type="submit" name="bressol_b2b_profile_submit">Opslaan</button></p>';
            echo '</form>';
        }
        echo '</div>';
        echo '<script>
            window.dataLayer = window.dataLayer || [];
            document.addEventListener("click", function(e) {
                var link = e.target.closest(".b2b-download");
                if (!link) return;
                e.preventDefault();
                window.dataLayer.push({
                    event: link.dataset.event || "b2b_download",
                    lead_id: ' . (int) $leadId . ',
                    source: "b2b"
                });
                window.location.href = link.href;
            }, {capture: true});
        </script>';
        if ($consentJustGiven) {
            echo '<script>window.dataLayer.push({event:"b2b_consent_given", lead_id:' . (int) $leadId . ', source:"b2b"});</script>';
        }
        echo '</body></html>';
        exit;
    }

    private function handle_signup_request(): void
    {
        $success = false;
        $errors = [];
        $leadId = 0;
        $token = '';

        if ($this->is_signup_submission()) {
            if (!$this->verify_signup_nonce()) {
                $this->render_error(self::GENERIC_ERROR);
                return;
            }

            $payload = $this->get_signup_payload($_POST);
            if (!$payload['consent']) {
                $errors[] = 'Toestemming is verplicht.';
            }

            $emailLower = strtolower($payload['email']);
            if ($emailLower === '' || !is_email($payload['email'])) {
                $errors[] = 'Vul een geldig e-mailadres in.';
            }

            if ($errors === [] && $this->is_signup_throttled($emailLower)) {
                $errors[] = 'Probeer later opnieuw.';
            }

            if ($errors === []) {
                $this->touch_signup_throttle($emailLower);
                $result = $this->leadService->register_signup([
                    'email' => $payload['email'],
                    'business_type' => $payload['business_type'],
                ]);
                $success = $result['lead_id'] > 0;
                $leadId = $result['lead_id'];
                if ($success) {
                    $lead = $this->leads->find_by_id($leadId);
                    $token = (string) ($lead['consent_token'] ?? '');
                } else {
                    $errors = $result['errors'] !== [] ? $result['errors'] : ['Er ging iets mis.'];
                }
            }
        }

        $this->render_signup_page($success, $errors, $leadId, $token);
    }

    /** @param array<int, string> $errors */
    private function render_signup_page(bool $success, array $errors, int $leadId, string $token): void
    {
        status_header(200);
        nocache_headers();

        $title = 'B2B aanmelden';
        $catalogUrl = $token !== '' ? add_query_arg(['token' => $token], home_url('/b2b/catalog')) : '';
        $pricelistUrl = $token !== '' ? add_query_arg(['token' => $token], home_url('/b2b/pricelist')) : '';

        echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html($title) . '</title></head><body>';
        echo '<div style="max-width:640px;margin:40px auto;font-family:Arial, sans-serif;">';
        echo '<h1>' . esc_html($title) . '</h1>';

        if ($success) {
            echo '<p><strong>Bedankt! Je aanmelding is ontvangen.</strong></p>';
            if ($catalogUrl !== '' && $pricelistUrl !== '') {
                echo '<p>Je kunt de documenten hieronder bekijken:</p>';
                echo '<ul>';
                echo '<li><a href="' . esc_url($catalogUrl) . '">Catalogus downloaden</a></li>';
                echo '<li><a href="' . esc_url($pricelistUrl) . '">Prijslijst downloaden</a></li>';
                echo '</ul>';
            }
        } else {
            if ($errors !== []) {
                echo '<div style="color:#b32d2e;">' . esc_html(implode(' ', $errors)) . '</div>';
            }
            echo '<form method="post">';
            wp_nonce_field('bressol_b2b_signup');
            echo '<p><label>Email *</label><br/><input type="email" name="email" required /></p>';
            echo '<p><label>Type bedrijf</label><br/>' . $this->render_business_type_select('business_type') . '</p>';
            echo '<p><label><input type="checkbox" name="consent" value="1" required> Ik geef toestemming om marketinginformatie van Bressol te ontvangen.</label></p>';
            echo '<p><button type="submit" name="bressol_b2b_signup_submit">Aanmelden</button></p>';
            echo '</form>';
        }

        echo '</div>';
        if ($success) {
            echo '<script>window.dataLayer = window.dataLayer || [];';
            echo 'window.dataLayer.push({event:"b2b_signup_submitted", lead_id:' . (int) $leadId . ', source:"b2b"});</script>';
        }
        echo '</body></html>';
        exit;
    }

    private function render_error(string $message): void
    {
        status_header(200);
        nocache_headers();
        echo '<!doctype html><html><head><meta charset="utf-8"><title>B2B</title></head><body>';
        echo '<div style="max-width:640px;margin:40px auto;font-family:Arial, sans-serif;">';
        echo '<h1>B2B</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div></body></html>';
        exit;
    }

    /** @param array<string, mixed> $lead */
    private function is_token_valid(array $lead): bool
    {
        $expires = (string) ($lead['consent_token_expires_at'] ?? '');
        if ($expires === '') {
            return false;
        }
        return strtotime($expires) >= current_time('timestamp');
    }

    public function maybe_flush_rewrite(): void
    {
        if (!is_admin()) {
            return;
        }
        $flag = get_option('bressol_b2b_flush_needed', '');
        if ($flag !== '1') {
            return;
        }
        flush_rewrite_rules(false);
        delete_option('bressol_b2b_flush_needed');
    }

    private function normalize_token(string $token): string
    {
        return strtolower(trim($token));
    }

    private function is_token_format_valid(string $token): bool
    {
        return $token !== '' && preg_match(self::TOKEN_REGEX, $token) === 1;
    }

    private function tokens_match(string $provided, string $stored): bool
    {
        if ($provided === '' || $stored === '') {
            return false;
        }
        return hash_equals($stored, $provided);
    }

    private function is_throttled(string $token): bool
    {
        $key = $this->throttle_key($token);
        return (bool) get_transient($key);
    }

    private function touch_throttle(string $token): void
    {
        $key = $this->throttle_key($token);
        set_transient($key, '1', self::CONSENT_THROTTLE_SECONDS);
    }

    private function throttle_key(string $token): string
    {
        return 'bressol_b2b_consent_' . hash('sha256', $token);
    }

    private function is_signup_throttled(string $emailLower): bool
    {
        if ($emailLower === '') {
            return false;
        }
        $key = 'bressol_b2b_signup_' . hash('sha256', $emailLower . '|' . $this->client_ip());
        return (bool) get_transient($key);
    }

    private function touch_signup_throttle(string $emailLower): void
    {
        $key = 'bressol_b2b_signup_' . hash('sha256', $emailLower . '|' . $this->client_ip());
        set_transient($key, '1', self::SIGNUP_THROTTLE_SECONDS);
    }

    private function is_profile_throttled(int $leadId): bool
    {
        if ($leadId <= 0) {
            return false;
        }
        $key = 'bressol_b2b_profile_' . $leadId;
        return (bool) get_transient($key);
    }

    private function touch_profile_throttle(int $leadId): void
    {
        if ($leadId <= 0) {
            return;
        }
        $key = 'bressol_b2b_profile_' . $leadId;
        set_transient($key, '1', self::PROFILE_THROTTLE_SECONDS);
    }


    /** @param array<string, mixed> $input
     *  @return array{email:string,business_type:string,consent:bool}
     */
    private function get_signup_payload(array $input): array
    {
        return [
            'email' => isset($input['email']) ? sanitize_email((string) wp_unslash($input['email'])) : '',
            'business_type' => isset($input['business_type']) ? sanitize_key((string) wp_unslash($input['business_type'])) : '',
            'consent' => !empty($input['consent']),
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array{company_name:string,city:string,phone:string}
     */
    private function get_profile_payload(array $input): array
    {
        return [
            'company_name' => isset($input['company_name']) ? sanitize_text_field((string) wp_unslash($input['company_name'])) : '',
            'city' => isset($input['city']) ? sanitize_text_field((string) wp_unslash($input['city'])) : '',
            'phone' => isset($input['phone']) ? sanitize_text_field((string) wp_unslash($input['phone'])) : '',
        ];
    }


    private function render_business_type_select(string $name): string
    {
        $options = [
            '' => 'Kies een optie',
            'gourmet' => 'Gourmet',
            'horeca' => 'Horeca',
            'corporate' => 'Corporate',
            'other' => 'Other',
        ];
        $html = '<select name="' . esc_attr($name) . '">';
        foreach ($options as $key => $label) {
            $html .= '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function client_ip(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        return preg_replace('/[^0-9a-fA-F:.,]/', '', $ip) ?? '';
    }

    private function is_consent_submission(): bool
    {
        return $this->is_post_request() && isset($_POST['bressol_b2b_consent_submit']);
    }

    private function verify_consent_nonce(): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';
        return $nonce !== '' && wp_verify_nonce($nonce, 'bressol_b2b_consent');
    }

    private function is_signup_submission(): bool
    {
        return $this->is_post_request() && isset($_POST['bressol_b2b_signup_submit']);
    }

    private function verify_signup_nonce(): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';
        return $nonce !== '' && wp_verify_nonce($nonce, 'bressol_b2b_signup');
    }

    private function is_profile_submission(): bool
    {
        return $this->is_post_request() && isset($_POST['bressol_b2b_profile_submit']);
    }

    private function verify_profile_nonce(): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';
        return $nonce !== '' && wp_verify_nonce($nonce, 'bressol_b2b_profile');
    }

    private function is_post_request(): bool
    {
        return $this->request_method() === 'POST';
    }

    private function is_get_request(): bool
    {
        return $this->request_method() === 'GET';
    }

    private function request_method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /** @return array{0:string,1:string,2:string} */
    private function get_pdf_info(string $page): array
    {
        $uploads = wp_upload_dir();
        $baseDir = (string) ($uploads['basedir'] ?? '');
        if ($baseDir === '') {
            return ['', '', ''];
        }
        $dir = rtrim($baseDir, '/') . '/b2b';
        if ($page === 'catalog') {
            return [$dir . '/catalog.pdf', 'catalog.pdf', 'b2b_catalog_clicked'];
        }
        return [$dir . '/pricelist.pdf', 'pricelist.pdf', 'b2b_pricelist_clicked'];
    }
}
