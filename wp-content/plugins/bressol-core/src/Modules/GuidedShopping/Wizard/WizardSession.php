<?php
declare(strict_types=1);

namespace Bressol\Modules\GuidedShopping\Wizard;

if (!defined('ABSPATH')) {
    exit;
}

final class WizardSession
{
    private const KEY = 'bressol_guided_shopping';

    public function set(string $key, string $value): void
    {
        $data = $this->all();
        $data[$key] = $value;
        $this->write($data);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $data = $this->all();
        return isset($data[$key]) ? (string) $data[$key] : $default;
    }

    public function all(): array
    {
        // Woo session (preferido)
        if (function_exists('WC') && WC()->session) {
            $data = WC()->session->get(self::KEY);
            return is_array($data) ? $data : [];
        }

        // Fallback: PHP session (solo para desarrollo / casos sin WC)
        if (PHP_SESSION_NONE === session_status()) {
            @session_start();
        }

        $data = $_SESSION[self::KEY] ?? [];
        return is_array($data) ? $data : [];
    }

    public function clear(): void
    {
        if (function_exists('WC') && WC()->session) {
            WC()->session->__unset(self::KEY);
            return;
        }

        if (PHP_SESSION_NONE === session_status()) {
            @session_start();
        }

        unset($_SESSION[self::KEY]);
    }

    private function write(array $data): void
    {
        if (function_exists('WC') && WC()->session) {
            WC()->session->set(self::KEY, $data);
            return;
        }

        if (PHP_SESSION_NONE === session_status()) {
            @session_start();
        }

        $_SESSION[self::KEY] = $data;
    }
}