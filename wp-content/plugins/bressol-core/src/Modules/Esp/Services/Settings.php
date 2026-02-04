<?php
declare(strict_types=1);

namespace Bressol\Modules\Esp\Services;

use PHPMailer\PHPMailer\PHPMailer;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings
{
    private const OPTION_NAME = 'bressol_esp_settings';
    private static bool $smtpAllowed = false;

    /** @return array<string, bool|int|string> */
    public function get_defaults(): array
    {
        return [
            'enabled' => false,
            'from_name' => 'Bressol',
            'from_email' => '',
            'reply_to' => '',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_secure' => 'tls',
            'daily_cap' => 200,
            'batch_size' => 25,
        ];
    }

    /** @return array<string, bool|int|string> */
    public function get_settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $merged = array_merge($this->get_defaults(), $settings);
        $merged['enabled'] = (bool) ($merged['enabled'] ?? false);
        $merged['smtp_port'] = (int) ($merged['smtp_port'] ?? 587);
        $merged['daily_cap'] = max(0, (int) ($merged['daily_cap'] ?? 200));
        $merged['batch_size'] = max(1, (int) ($merged['batch_size'] ?? 25));

        return $merged;
    }

    public function ensure_defaults(): void
    {
        $current = get_option(self::OPTION_NAME, null);
        if ($current === null) {
            update_option(self::OPTION_NAME, $this->get_defaults(), false);
        }
    }

    public function save(array $data): void
    {
        $settings = $this->get_settings();

        $settings['enabled'] = !empty($data['enabled']);
        $settings['from_name'] = sanitize_text_field((string) ($data['from_name'] ?? ''));
        $settings['from_email'] = sanitize_email((string) ($data['from_email'] ?? ''));
        $settings['reply_to'] = sanitize_email((string) ($data['reply_to'] ?? ''));
        $settings['smtp_host'] = sanitize_text_field((string) ($data['smtp_host'] ?? ''));
        $settings['smtp_port'] = max(1, (int) ($data['smtp_port'] ?? 587));
        $settings['smtp_user'] = sanitize_text_field((string) ($data['smtp_user'] ?? ''));
        $settings['smtp_pass'] = (string) ($data['smtp_pass'] ?? '');
        $smtpSecure = sanitize_text_field((string) ($data['smtp_secure'] ?? 'tls'));
        $settings['smtp_secure'] = in_array($smtpSecure, ['tls', 'ssl', 'none'], true) ? $smtpSecure : 'tls';
        $settings['daily_cap'] = max(0, (int) ($data['daily_cap'] ?? 200));
        $settings['batch_size'] = max(1, (int) ($data['batch_size'] ?? 25));

        update_option(self::OPTION_NAME, $settings, false);
    }

    public function apply_smtp_settings(PHPMailer $phpmailer): void
    {
        if (!self::is_smtp_allowed()) {
            return;
        }
        $settings = $this->get_settings();
        if (!$settings['enabled']) {
            return;
        }

        $fromEmail = (string) $settings['from_email'];
        $fromName = (string) $settings['from_name'];
        if ($fromEmail !== '') {
            $phpmailer->setFrom($fromEmail, $fromName, false);
        }
        $replyTo = (string) $settings['reply_to'];
        if ($replyTo !== '' && $replyTo !== $fromEmail) {
            $phpmailer->addReplyTo($replyTo, $fromName);
        }

        $host = (string) $settings['smtp_host'];
        if ($host === '') {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = (int) $settings['smtp_port'];

        $smtpUser = (string) $settings['smtp_user'];
        $smtpPass = (string) $settings['smtp_pass'];

        $phpmailer->SMTPAuth = ($smtpUser !== '' || $smtpPass !== '');
        if ($phpmailer->SMTPAuth) {
            $phpmailer->Username = $smtpUser;
            $phpmailer->Password = $smtpPass;
        }

        $secure = (string) $settings['smtp_secure'];
        if ($secure === 'none') {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        } else {
            $phpmailer->SMTPSecure = $secure;
        }
    }

    public static function set_smtp_allowed(bool $allowed): void
    {
        self::$smtpAllowed = $allowed;
    }

    public static function is_smtp_allowed(): bool
    {
        return self::$smtpAllowed;
    }
}
