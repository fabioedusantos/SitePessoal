<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Env;
use App\Http\JsonResponse;
use App\Security\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AdminAuthMiddleware
{
    private const COOKIE = 'fbsantos_admin';
    private const PRODUCTION_COOKIE = '__Host-fbsantos_admin';
    private const ADMIN_USER_ID = 1;

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->tokenFromRequest($request);

        if ($token === '') {
            return JsonResponse::write(new Response(), ['message' => 'Não autorizado.'], 401);
        }

        try {
            $jwt = JwtService::fromEnv();
            $claims = $jwt->decode($token);
        } catch (\Throwable) {
            return JsonResponse::write(new Response(), ['message' => 'Sessão inválida ou expirada.'], 401);
        }

        if (!$this->activeAdmin((int) $claims->sub, (string) $claims->email)) {
            return JsonResponse::write(new Response(), ['message' => 'Sessão inválida ou expirada.'], 401);
        }

        $configuredHash = trim((string) Env::get('ADMIN_PASSWORD_HASH', ''));
        $expectedFingerprint = $jwt->credentialFingerprint($configuredHash);
        if (!isset($claims->authv) || !hash_equals($expectedFingerprint, (string) $claims->authv)) {
            return JsonResponse::write(new Response(), ['message' => 'Sessão inválida ou expirada.'], 401);
        }

        if ($this->requiresCsrf($request)) {
            $csrf = $request->getHeaderLine('X-CSRF-Token');
            if ($csrf === '' || !isset($claims->csrf) || !hash_equals((string) $claims->csrf, $csrf)) {
                return JsonResponse::write(new Response(), ['message' => 'Validação de segurança inválida.'], 403);
            }
        }

        return $handler->handle($request->withAttribute('admin', $claims));
    }

    private function tokenFromRequest(ServerRequestInterface $request): string
    {
        $cookies = $request->getCookieParams();
        $cookieName = strtolower((string) Env::get('APP_ENV', 'development')) === 'production'
            ? self::PRODUCTION_COOKIE
            : self::COOKIE;
        return isset($cookies[$cookieName]) ? (string) $cookies[$cookieName] : '';
    }

    private function activeAdmin(int $id, string $email): bool
    {
        $configuredEmail = mb_strtolower(trim((string) Env::get('ADMIN_EMAIL', '')));
        return $id === self::ADMIN_USER_ID
            && filter_var($configuredEmail, FILTER_VALIDATE_EMAIL) !== false
            && hash_equals($configuredEmail, mb_strtolower($email));
    }

    private function requiresCsrf(ServerRequestInterface $request): bool
    {
        return !in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
