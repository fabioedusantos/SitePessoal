<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PublicProjectController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->database->pdo()->query(
            "SELECT p.*, o.name organization, o.slug organization_slug, o.image_url organization_image_url
             FROM projects p
             LEFT JOIN organizations o ON o.id = p.organization_id
             WHERE p.status = 'published'
             ORDER BY p.featured DESC, p.sort_order, p.id DESC"
        )->fetchAll();

        return JsonResponse::write($response, ['data' => $rows]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT p.*, o.name organization, o.slug organization_slug, o.image_url organization_image_url
             FROM projects p
             LEFT JOIN organizations o ON o.id = p.organization_id
             WHERE p.slug = ? AND p.status = 'published'
             LIMIT 1"
        );
        $stmt->execute([$args['slug']]);
        $project = $stmt->fetch();

        if (!$project) {
            return JsonResponse::write($response, ['message' => 'Projeto não encontrado.'], 404);
        }

        $tech = $this->database->pdo()->prepare(
            "SELECT t.id, t.slug, t.name, t.category
             FROM technologies t
             JOIN project_technology pt ON pt.technology_id = t.id
             WHERE pt.project_id = ? AND t.is_visible = 1
             ORDER BY t.name"
        );
        $tech->execute([(int) $project['id']]);
        $project['technologies'] = $tech->fetchAll();

        $links = $this->database->pdo()->prepare(
            "SELECT id, label, url, kind
             FROM project_links
             WHERE project_id = ? AND is_visible = 1 AND NULLIF(TRIM(url), '') IS NOT NULL
             ORDER BY sort_order, id"
        );
        $links->execute([(int) $project['id']]);
        $project['links'] = $links->fetchAll();

        $media = $this->database->pdo()->prepare(
            "SELECT id, media_url, alt_text, caption, kind
             FROM project_media
             WHERE project_id = ? AND is_visible = 1 AND NULLIF(TRIM(media_url), '') IS NOT NULL
             ORDER BY sort_order, id"
        );
        $media->execute([(int) $project['id']]);
        $project['media'] = $media->fetchAll();

        $project['related_contexts'] = $this->relatedContexts((int) $project['id']);
        $project['related_experiences'] = array_values(array_filter(
            $project['related_contexts'],
            static fn (array $item): bool => ($item['_relation_type'] ?? null) === 'experience'
        ));

        $sections = $this->database->pdo()->prepare(
            "SELECT id, eyebrow, title, body, items
             FROM project_sections
             WHERE project_id = ? AND is_visible = 1
             ORDER BY sort_order, id"
        );
        $sections->execute([(int) $project['id']]);
        $project['sections'] = array_map(static function (array $section): array {
            $section['items'] = $section['items'] ? json_decode($section['items'], true) : [];
            return $section;
        }, $sections->fetchAll());

        return JsonResponse::write($response, ['data' => $project]);
    }

    private function relatedContexts(int $projectId): array
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            "SELECT entity_type,entity_id,relation_label,sort_order
             FROM project_relations
             WHERE project_id=? AND is_visible=1
             ORDER BY sort_order,id"
        );
        $stmt->execute([$projectId]);

        $result = [];
        foreach ($stmt->fetchAll() as $relation) {
            $type = (string) ($relation['entity_type'] ?? '');
            $entityId = (int) ($relation['entity_id'] ?? 0);
            $entity = null;

            if ($type === 'experience') {
                $entityStmt = $pdo->prepare(
                    "SELECT e.*,o.name organization,o.slug organization_slug,o.image_url organization_image_url
                     FROM experiences e JOIN organizations o ON o.id=e.organization_id
                     WHERE e.id=? AND e.is_visible=1 LIMIT 1"
                );
                $entityStmt->execute([$entityId]);
                $entity = $entityStmt->fetch() ?: null;
            } elseif ($type === 'education') {
                $entityStmt = $pdo->prepare('SELECT * FROM education WHERE id=? AND is_visible=1 LIMIT 1');
                $entityStmt->execute([$entityId]);
                $entity = $entityStmt->fetch() ?: null;
            } elseif ($type === 'certification') {
                $entityStmt = $pdo->prepare('SELECT * FROM certifications WHERE id=? AND is_visible=1 LIMIT 1');
                $entityStmt->execute([$entityId]);
                $entity = $entityStmt->fetch() ?: null;
            }

            if (!$entity) {
                continue;
            }

            $entity['_relation_type'] = $type;
            $entity['_relation_label'] = $relation['relation_label'] ?: null;
            $entity['_relation_sort_order'] = (int) $relation['sort_order'];
            $result[] = $entity;
        }

        return $result;
    }
}
