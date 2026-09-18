<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PublicCareerContentController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function publications(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $publications = $this->database->pdo()->query(
            "SELECT id, slug, title, kind, organization, event_name, location, published_date,
                    summary, description, external_url
             FROM publications
             WHERE is_visible = 1
             ORDER BY sort_order, published_date DESC, id"
        )->fetchAll();

        $mediaStmt = $this->database->pdo()->prepare(
            "SELECT id, title, media_type, media_url, embed_url
             FROM publication_media
             WHERE publication_id = ? AND is_visible = 1
             ORDER BY sort_order, id"
        );

        $sectionsStmt = $this->database->pdo()->prepare(
            "SELECT id, eyebrow, title, body, items
             FROM publication_sections
             WHERE publication_id = ? AND is_visible = 1
             ORDER BY sort_order, id"
        );

        $projectsStmt = $this->database->pdo()->prepare(
            "SELECT p.id, p.slug, p.name, p.period_label, p.context, pp.relation_label
             FROM publication_projects pp
             JOIN projects p ON p.id = pp.project_id
             WHERE pp.publication_id = ? AND pp.is_visible = 1 AND p.status = 'published'
             ORDER BY pp.sort_order, p.sort_order, p.id"
        );

        foreach ($publications as &$publication) {
            $publicationId = (int) $publication['id'];

            $mediaStmt->execute([$publicationId]);
            $publication['media'] = $mediaStmt->fetchAll();

            $sectionsStmt->execute([$publicationId]);
            $publication['sections'] = array_map(static function (array $section): array {
                $section['items'] = $section['items'] ? json_decode($section['items'], true) : [];
                return $section;
            }, $sectionsStmt->fetchAll());

            $projectsStmt->execute([$publicationId]);
            $publication['related_projects'] = $projectsStmt->fetchAll();
        }
        unset($publication);

        return JsonResponse::write($response, ['data' => $publications]);
    }

    public function evaluationBoards(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->database->pdo()->query(
            "SELECT id, slug, title, institution, logo_url, event_date, description
             FROM evaluation_boards
             WHERE is_visible = 1
             ORDER BY event_date DESC, sort_order, id"
        )->fetchAll();

        return JsonResponse::write($response, ['data' => $rows]);
    }
}
