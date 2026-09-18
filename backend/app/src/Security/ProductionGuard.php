<?php

declare(strict_types=1);

namespace App\Security;

use App\Config\Env;
use RuntimeException;

final class ProductionGuard
{
    public static function assertSafe(): void
    {
        if (strtolower((string) Env::get('APP_ENV', 'development')) !== 'production') {
            return;
        }

        $errors = [];
        if (Env::bool('APP_DEBUG', false)) {
            $errors[] = 'APP_DEBUG deve ser false';
        }
        if (!Env::bool('TRUST_PROXY_HEADERS', false)) {
            $errors[] = 'TRUST_PROXY_HEADERS deve ser true atrás dos proxies de produção';
        }

        $allowedHosts = array_values(array_filter(array_map('trim', explode(',', (string) Env::get('API_ALLOWED_HOSTS', '')))));
        if (!$allowedHosts || in_array('*', $allowedHosts, true)) {
            $errors[] = 'API_ALLOWED_HOSTS deve conter somente hosts explícitos';
        }
        foreach ($allowedHosts as $host) {
            $normalized = strtolower($host);
            if ($normalized === 'localhost' || str_starts_with($normalized, '127.') || str_contains($normalized, '://')) {
                $errors[] = 'API_ALLOWED_HOSTS não deve conter localhost, IP loopback ou esquema em produção';
                break;
            }
        }

        $jwtSecret = trim((string) Env::get('JWT_SECRET', ''));
        if (strlen($jwtSecret) < 48 || self::looksLikePlaceholder($jwtSecret)) {
            $errors[] = 'JWT_SECRET deve possuir pelo menos 48 caracteres aleatórios';
        }

        $adminEmail = mb_strtolower(trim((string) Env::get('ADMIN_EMAIL', '')));
        $adminPasswordHash = trim((string) Env::get('ADMIN_PASSWORD_HASH', ''));
        $adminDisplayName = trim((string) Env::get('ADMIN_DISPLAY_NAME', ''));
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ADMIN_EMAIL deve conter um e-mail válido';
        }
        $passwordInfo = password_get_info($adminPasswordHash);
        if (($passwordInfo['algoName'] ?? '') !== 'bcrypt' || (int) ($passwordInfo['options']['cost'] ?? 0) < 12) {
            $errors[] = 'ADMIN_PASSWORD_HASH deve ser bcrypt com custo mínimo 12; gere com php bin/hash-admin-password.php';
        }
        if ($adminDisplayName === '' || mb_strlen($adminDisplayName) > 120) {
            $errors[] = 'ADMIN_DISPLAY_NAME deve possuir entre 1 e 120 caracteres';
        }

        $databaseUser = strtolower(trim((string) Env::get('MARIADB_USER', '')));
        if ($databaseUser === '' || $databaseUser === 'root') {
            $errors[] = 'MARIADB_USER deve ser um usuário dedicado da aplicação, diferente de root';
        }
        foreach (['MARIADB_PASSWORD', 'MARIADB_ROOT_PASSWORD'] as $key) {
            $value = trim((string) Env::get($key, ''));
            if ($value === '' || strlen($value) < 16 || self::looksLikePlaceholder($value)) {
                $errors[] = "{$key} precisa ser definido com valor aleatório de pelo menos 16 caracteres";
            }
        }

        if (!Env::bool('HCAPTCHA_ENABLED', true)) {
            $errors[] = 'HCAPTCHA_ENABLED deve permanecer true em produção';
        }
        $captchaSecret = trim((string) Env::get('HCAPTCHA_SECRET', ''));
        $captchaSiteKey = trim((string) Env::get('HCAPTCHA_SITEKEY', ''));
        if ($captchaSecret === '' || self::looksLikePlaceholder($captchaSecret)) {
            $errors[] = 'HCAPTCHA_SECRET precisa ser configurado com a credencial real';
        }
        if ($captchaSiteKey === '' || self::looksLikePlaceholder($captchaSiteKey)) {
            $errors[] = 'HCAPTCHA_SITEKEY precisa ser configurado com a credencial real';
        }

        $origins = array_values(array_filter(array_map('trim', explode(',', (string) Env::get('CORS_ALLOWED_ORIGINS', '')))));
        if (!$origins || in_array('*', $origins, true)) {
            $errors[] = 'CORS_ALLOWED_ORIGINS deve conter somente origens explícitas';
        }
        foreach ($origins as $origin) {
            $parts = parse_url($origin);
            $host = strtolower((string) ($parts['host'] ?? ''));
            if (($parts['scheme'] ?? '') !== 'https' || $host === '' || $host === 'localhost' || str_starts_with($host, '127.')) {
                $errors[] = 'CORS_ALLOWED_ORIGINS deve usar somente origens HTTPS reais em produção';
                break;
            }
        }

        if (Env::bool('MAIL_ENABLED', false)) {
            $mailHost = trim((string) Env::get('MAIL_HOST', ''));
            $mailPort = (int) Env::get('MAIL_PORT', 0);
            $mailUsername = trim((string) Env::get('MAIL_USERNAME', ''));
            $mailPassword = trim((string) Env::get('MAIL_PASSWORD', ''));
            $mailFrom = trim((string) Env::get('MAIL_FROM_EMAIL', ''));
            $contactTo = trim((string) Env::get('CONTACT_TO_EMAIL', ''));

            if ($mailHost === '' || preg_match('/[\r\n]/', $mailHost) || $mailPort < 1 || $mailPort > 65535) {
                $errors[] = 'MAIL_HOST e MAIL_PORT precisam ser válidos quando MAIL_ENABLED=true';
            }
            if ($mailUsername === '' || preg_match('/[\r\n]/', $mailUsername)) {
                $errors[] = 'MAIL_USERNAME precisa ser configurado quando MAIL_ENABLED=true';
            }
            if ($mailPassword === '' || self::looksLikePlaceholder($mailPassword)) {
                $errors[] = 'MAIL_PASSWORD precisa ser configurado quando MAIL_ENABLED=true';
            }
            if (!filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'MAIL_FROM_EMAIL precisa ser um e-mail válido quando MAIL_ENABLED=true';
            }
            if (!filter_var($contactTo, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'CONTACT_TO_EMAIL precisa ser um e-mail válido quando MAIL_ENABLED=true';
            }
        }

        $propertyId = trim((string) Env::get('GA_PROPERTY_ID', ''));
        $serviceEmail = trim((string) Env::get('GA_SERVICE_ACCOUNT_EMAIL', ''));
        $privateKeyB64 = trim((string) Env::get('GA_SERVICE_ACCOUNT_PRIVATE_KEY_B64', ''));
        $gaValues = [$propertyId, $serviceEmail, $privateKeyB64];
        $configuredGaValues = count(array_filter($gaValues, static fn (string $value): bool => $value !== ''));
        if ($configuredGaValues > 0 && $configuredGaValues < count($gaValues)) {
            $errors[] = 'GA_PROPERTY_ID, GA_SERVICE_ACCOUNT_EMAIL e GA_SERVICE_ACCOUNT_PRIVATE_KEY_B64 devem ser configurados em conjunto';
        } elseif ($configuredGaValues === count($gaValues)) {
            $decodedKey = base64_decode($privateKeyB64, true);
            if (!ctype_digit($propertyId) || !filter_var($serviceEmail, FILTER_VALIDATE_EMAIL)
                || !is_string($decodedKey) || !str_contains($decodedKey, 'PRIVATE KEY')) {
                $errors[] = 'A configuração da conta de serviço do Google Analytics é inválida';
            }
        }

        if ($errors) {
            throw new RuntimeException('Configuração insegura de produção: ' . implode('; ', array_unique($errors)) . '.');
        }
    }

    private static function looksLikePlaceholder(string $value): bool
    {
        $normalized = strtolower(trim($value));
        foreach (['change', 'replace', 'example', 'changeme', 'password', 'secret-here', 'your-'] as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }
        return false;
    }
}
