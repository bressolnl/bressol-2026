<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Frontend;

use Bressol\Modules\B2B\Repositories\LeadRepository;
use Bressol\Modules\B2B\Services\LeadService;

if (!defined('ABSPATH')) {
    exit;
}

final class Endpoints
{
    private const TOKEN_REGEX = '/^[a-f0-9]{64}$/';
    private const CONSENT_THROTTLE_SECONDS = 60;
    private const GENERIC_ERROR = 'Deze link is ongeldig of verlopen.';

    private LeadRepository $leads;
    private LeadService $leadService;

    public function __construct()
    {
        $this->leads = new LeadRepository();
        $this->leadService = new LeadService();
    }

    public function register_rewrite(): void
    {
        add_rewrite_rule('^b2b/catalog/?$', 'index.php?bressol_b2b=catalog', 'top');
        add_rewrite_rule('^b2b/pricelist/?$', 'index.php?bressol_b2b=pricelist', 'top');
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
        if (!in_array($page, ['catalog', 'pricelist'], true)) {
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
        if ($this->is_get_request() && $download === '1' && $consented) {
            $this->handle_download($page, (int) $lead['id']);
            return;
        }

        $this->render_page($page, $token, $consented, $consentJustGiven, (int) $lead['id']);
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

    private function render_page(string $page, string $token, bool $consented, bool $consentJustGiven, int $leadId): void
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

    private function is_consent_submission(): bool
    {
        return $this->is_post_request() && isset($_POST['bressol_b2b_consent_submit']);
    }

    private function verify_consent_nonce(): bool
    {
        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';
        return $nonce !== '' && wp_verify_nonce($nonce, 'bressol_b2b_consent');
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
