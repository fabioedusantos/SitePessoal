<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminDashboardController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $pdo = $this->database->pdo();
        $counts = [];
        foreach ([
            'experiences',
            'organizations',
            'projects',
            'technologies',
            'education',
            'certifications',
            'publications',
            'evaluation_boards',
            'pages',
        ] as $table) {
            $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        }

        return JsonResponse::write($response, ['counts' => $counts]);
    }
}
