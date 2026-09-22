<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Env;

final class HCaptchaVerifier
{
    private const VERIFY_URL = 'https://api.hcaptcha.com/siteverify';

    public function verify(string $token, ?string $remoteIp = null): bool
    {
        if (!Env::bool('HCAPTCHA_ENABLED', true)) {
            return true;
        }

        $isDevelopment = strtolower((string) Env::get('APP_ENV', 'production')) === 'development';
        $defaultSiteKey = $isDevelopment ? '10000000-ffff-ffff-ffff-000000000001' : '';
        $defaultSecret = $isDevelopment ? '0x0000000000000000000000000000000000000000' : '';
        $secret = trim((string) Env::get('HCAPTCHA_SECRET', $defaultSecret));
        $siteKey = trim((string) Env::get('HCAPTCHA_SITEKEY', $defaultSiteKey));
        if ($token === '' || $secret === '' || $siteKey === '') {
            return false;
        }

        $payload = [
            'secret' => $secret,
            'response' => $token,
            'sitekey' => $siteKey,
        ];
        if ($remoteIp && filter_var($remoteIp, FILTER_VALIDATE_IP)) {
            $payload['remoteip'] = $remoteIp;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $result = @file_get_contents(self::VERIFY_URL, false, $context);
        if ($result === false) {
            return false;
        }

        try {
            $decoded = json_decode($result, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return ($decoded['success'] ?? false) === true;
    }
}
