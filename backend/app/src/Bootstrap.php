<?php

declare(strict_types=1);

namespace App;

use App\Config\Env;
use App\Http\JsonResponse;
use App\Infrastructure\Database;
use App\Security\ProductionGuard;
use DI\Container;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

final class Bootstrap
{
    public static function create(Container $container): App
    {
        self::loadEnvironment();
        ProductionGuard::assertSafe();

        $container->set(Database::class, fn () => new Database());

        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $displayErrors = Env::bool('APP_DEBUG', false)
            && strtolower((string) Env::get('APP_ENV', 'development')) !== 'production';
        $app->addErrorMiddleware($displayErrors, true, true);

        self::securityHeaders($app);
        self::hostGuard($app);
        self::cors($app);
        (require dirname(__DIR__) . '/config/routes.php')($app);

        return $app;
    }

    private static function loadEnvironment(): void
    {
        // O Docker injeta as variáveis de produção via env_file. O arquivo
        // local permanece suportado apenas para execuções PHP fora do Compose.
        $envFile = dirname(__DIR__) . '/.env';
        if (is_file($envFile)) {
            Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
        }
    }

    private static function securityHeaders(App $app): void
    {
        $app->add(function (ServerRequestInterface $request, $handler) {
            $response = $handler->handle($request)
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('X-Frame-Options', 'DENY')
                ->withHeader('Referrer-Policy', 'no-referrer')
                ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
                ->withHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");

            $path = $request->getUri()->getPath();
            if (str_starts_with($path, '/api/v1/admin') || str_starts_with($path, '/api/v1/auth') || $path === '/api/v1/public/contact') {
                $response = $response->withHeader('Cache-Control', 'no-store, max-age=0');
            }

            if (strtolower((string) Env::get('APP_ENV', 'development')) === 'production') {
                $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }

            return $response;
        });
    }

    private static function hostGuard(App $app): void
    {
        $app->add(function (ServerRequestInterface $request, $handler) {
            if (strtolower((string) Env::get('APP_ENV', 'development')) !== 'production') {
                return $handler->handle($request);
            }

            $allowed = array_values(array_filter(array_map(
                static fn (string $host): string => strtolower(trim($host)),
                explode(',', (string) Env::get('API_ALLOWED_HOSTS', ''))
            )));
            if (!$allowed) {
                return JsonResponse::write(new Response(), ['message' => 'Host não autorizado.'], 421);
            }

            $host = strtolower($request->getUri()->getHost());
            if ($host === '' || !in_array($host, $allowed, true)) {
                return JsonResponse::write(new Response(), ['message' => 'Host não autorizado.'], 421);
            }

            return $handler->handle($request);
        });
    }

    private static function cors(App $app): void
    {
        $app->add(function (ServerRequestInterface $request, $handler) {
            $response = $request->getMethod() === 'OPTIONS'
                ? new Response(204)
                : $handler->handle($request);

            $origin = trim($request->getHeaderLine('Origin'));
            $configured = trim((string) Env::get('CORS_ALLOWED_ORIGINS', ''));
            $allowed = $configured !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $configured))))
                : ['http://localhost:3000', 'http://localhost:5173'];

            if ($origin !== '' && in_array($origin, $allowed, true)) {
                $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
            }

            return $response
                ->withHeader('Vary', 'Origin')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-CSRF-Token')
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        });
    }
}
