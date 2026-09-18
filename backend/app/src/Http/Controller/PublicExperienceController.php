<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PublicExperienceController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->database->pdo()->query(
            "SELECT e.*, o.name organization, o.slug organization_slug,
                    o.image_url organization_image_url
             FROM experiences e
             JOIN organizations o ON o.id = e.organization_id
             WHERE e.is_visible = 1
             ORDER BY e.sort_order, e.start_date DESC"
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['highlights'] = $row['highlights'] ? json_decode($row['highlights'], true) : [];
            $row['technologies'] = $this->technologies((int) $row['id']);
        }

        return JsonResponse::write($response, ['data' => $rows]);
    }

    private function technologies(int $experienceId): array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT t.id, t.slug, t.name, t.category
             FROM technologies t
             JOIN experience_technology et ON et.technology_id = t.id
             WHERE et.experience_id = ? AND t.is_visible = 1
             ORDER BY t.name"
        );
        $stmt->execute([$experienceId]);
        return $stmt->fetchAll();
    }
}
