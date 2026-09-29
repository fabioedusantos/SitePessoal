<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Service\GoogleAnalyticsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminAnalyticsController
{
    public function __construct(private readonly GoogleAnalyticsService $analytics)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            return JsonResponse::write($response, $this->analytics->dashboard());
        } catch (\Throwable $exception) {
            return JsonResponse::write($response, [
                'configured' => true,
                'message' => 'Não foi possível consultar o Google Analytics agora.',
                'detail' => $exception->getMessage(),
            ], 502);
        }
    }
}
