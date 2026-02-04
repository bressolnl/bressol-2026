<?php
declare(strict_types=1);

namespace Bressol\Modules\B2B\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class Masking
{
    public static function mask_email(string $email): string
    {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }
        [$local, $domain] = explode('@', $email, 2);
        $localMasked = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 2)) . substr($local, -1);
        $domainParts = explode('.', $domain);
        $domainMasked = substr($domainParts[0], 0, 1) . str_repeat('*', max(1, strlen($domainParts[0]) - 2)) . substr($domainParts[0], -1);
        $suffix = count($domainParts) > 1 ? '.' . end($domainParts) : '';
        return $localMasked . '@' . $domainMasked . $suffix;
    }
}
