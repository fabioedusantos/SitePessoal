<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Config\Env;
use App\Http\JsonResponse;
use App\Security\ClientIp;
use App\Security\JwtService;
use App\Security\LoginThrottle;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    private const COOKIE = 'fbsantos_admin';
    private const PRODUCTION_COOKIE = '__Host-fbsantos_admin';
    private const ADMIN_USER_ID = 1;
    private const DUMMY_PASSWORD_HASH = '$2y$12$KUkQ8XjRz7DxYZ1hwQbzWOa67bseRL4Yh4fG10CBMhJeUCYRVvLsa';

    public function __construct(private readonly LoginThrottle $throttle)
    {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (!str_starts_with($contentType, 'application/json')) {
            return JsonResponse::write($response, ['message' => 'Formato de requisição inválido.'], 415);
        }

        $body = (array) $request->getParsedBody();
        $email = mb_strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190 || $password === '' || strlen($password) > 4096) {
            return JsonResponse::write($response, ['message' => 'E-mail ou senha inválidos.'], 401);
        }

        $ip = ClientIp::fromRequest($request) ?? 'unknown';
        $ipKey = 'ip:' . $ip;
        $accountKey = 'account:' . $email;

        try {
            $this->throttle->assertAllowed($ipKey);
            $this->throttle->assertAllowed($accountKey);
        } catch (\RuntimeException $e) {
            return JsonResponse::write($response, ['message' => $e->getMessage()], 429);
        }

        $configuredEmail = mb_strtolower(trim((string) Env::get('ADMIN_EMAIL', '')));
        $configuredHash = trim((string) Env::get('ADMIN_PASSWORD_HASH', ''));
        $displayName = trim((string) Env::get('ADMIN_DISPLAY_NAME', 'Administrador')) ?: 'Administrador';

        $passwordInfo = password_get_info($configuredHash);
        $hash = ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown'
            ? $configuredHash
            : self::DUMMY_PASSWORD_HASH;

        // Executa password_verify mesmo quando o e-mail não confere para reduzir diferença de timing.
        $emailValid = $configuredEmail !== '' && hash_equals($configuredEmail, $email);
        $passwordValid = password_verify($password, $hash);

        if (!$emailValid || !$passwordValid) {
            $this->throttle->registerFailure($ipKey);
            $this->throttle->registerFailure($accountKey);
            usleep(random_int(220000, 420000));
            return JsonResponse::write($response, ['message' => 'E-mail ou senha inválidos.'], 401);
        }

        $this->throttle->clear($ipKey);
        $this->throttle->clear($accountKey);
        $csrf = bin2hex(random_bytes(32));
        $jwt = JwtService::fromEnv();
        $token = $jwt->issue(
            self::ADMIN_USER_ID,
            $configuredEmail,
            $csrf,
            $jwt->credentialFingerprint($configuredHash)
        );

        $response = JsonResponse::write($response, [
            'csrf_token' => $csrf,
            'user' => [
                'id' => self::ADMIN_USER_ID,
                'email' => $configuredEmail,
                'display_name' => $displayName,
            ],
        ]);

        return $response->withAddedHeader('Set-Cookie', $this->cookieHeader($token, $jwt->ttlSeconds()));
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response = JsonResponse::write($response, ['ok' => true]);
        return $response->withAddedHeader('Set-Cookie', $this->cookieHeader('', 0));
    }

    private function cookieHeader(string $value, int $maxAge): string
    {
        $production = strtolower((string) Env::get('APP_ENV', 'development')) === 'production';
        $cookieName = $production ? self::PRODUCTION_COOKIE : self::COOKIE;
        $parts = [
            $cookieName . '=' . rawurlencode($value),
            'Path=/',
            'HttpOnly',
            'SameSite=Strict',
            'Max-Age=' . max(0, $maxAge),
        ];

        if ($maxAge === 0) {
            $parts[] = 'Expires=Thu, 01 Jan 1970 00:00:00 GMT';
        }
        if ($production) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }
}
