<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Infrastructure\Database;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PublicPageController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = trim((string) ($args['slug'] ?? 'home'), '/');
        if ($slug === '') {
            $slug = 'home';
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published' LIMIT 1");
        $stmt->execute([$slug]);
        $page = $stmt->fetch();
        if (!$page) {
            return JsonResponse::write($response, ['message' => 'Página não encontrada.'], 404);
        }

        $sectionStmt = $pdo->prepare(
            "SELECT * FROM page_sections WHERE page_id = ? AND is_visible = 1 ORDER BY sort_order, id"
        );
        $sectionStmt->execute([(int) $page['id']]);
        $sections = $sectionStmt->fetchAll();

        $itemStmt = $pdo->prepare(
            "SELECT * FROM page_section_items WHERE section_id = ? AND is_visible = 1 ORDER BY sort_order, id"
        );

        foreach ($sections as &$section) {
            $section['settings'] = $this->decodeJson($section['settings'] ?? null);
            $sourceType = (string) ($section['settings']['entity_type'] ?? '');
            $sourceId = (int) ($section['settings']['entity_id'] ?? 0);
            $sourceMode = (string) ($section['settings']['source'] ?? '');

            if ($sourceType !== '' && $sourceMode === 'all') {
                // The page builder setting is authoritative. Old explicit relations may
                // still exist after changing a section from selected items to "all";
                // they must not restrict the public collection.
                $items = $this->resolveCollection($sourceType);
            } else {
                $itemStmt->execute([(int) $section['id']]);
                $items = $itemStmt->fetchAll();
                foreach ($items as &$item) {
                    $item['settings'] = $this->decodeJson($item['settings'] ?? null);
                    if (!empty($item['entity_type']) && !empty($item['entity_id'])) {
                        $item['entity'] = $this->resolveEntity((string) $item['entity_type'], (int) $item['entity_id']);
                    }
                }
                unset($item);

                if (!$items && $sourceType !== '' && $sourceId > 0) {
                    $entity = $this->resolveEntity($sourceType, $sourceId);
                    if ($entity) {
                        $items[] = ['item_type' => 'entity', 'entity_type' => $sourceType, 'entity_id' => $sourceId, 'entity' => $entity, 'sort_order' => 10];
                    }
                }
            }
            $section['items'] = $items;
        }
        unset($section);

        return JsonResponse::write($response, ['page' => $page, 'sections' => $sections]);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->database->pdo()->query(
            "SELECT id,title,slug,updated_at FROM pages WHERE status='published' AND show_in_sitemap=1 AND noindex=0 ORDER BY sort_order,id"
        )->fetchAll();
        return JsonResponse::write($response, ['data' => $rows]);
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function resolveCollection(string $type): array
    {
        $pdo = $this->database->pdo();
        $map = [
            'project' => "SELECT id FROM projects WHERE status='published' ORDER BY featured DESC, sort_order, id",
            'experience' => "SELECT id FROM experiences WHERE is_visible=1 ORDER BY is_current DESC, start_date DESC, sort_order, id",
            'organization' => "SELECT id FROM organizations ORDER BY sort_order, id",
            'technology' => "SELECT id FROM technologies WHERE is_visible=1 ORDER BY category, sort_order, name",
            'education' => "SELECT id FROM education WHERE is_visible=1 ORDER BY sort_order, end_date DESC, id",
            'certification' => "SELECT id FROM certifications WHERE is_visible=1 ORDER BY sort_order, issued_date DESC, id",
            'publication' => "SELECT id FROM publications WHERE is_visible=1 ORDER BY sort_order, published_date DESC, id",
            'evaluation_board' => "SELECT id FROM evaluation_boards WHERE is_visible=1 ORDER BY sort_order, event_date DESC, id",
        ];
        if (!isset($map[$type])) {
            return [];
        }
        $rows = $pdo->query($map[$type])->fetchAll(PDO::FETCH_COLUMN);
        $result = [];
        $order = 10;
        foreach ($rows as $id) {
            $entity = $this->resolveEntity($type, (int) $id);
            if ($entity) {
                $result[] = ['item_type' => 'entity', 'entity_type' => $type, 'entity_id' => (int) $id, 'entity' => $entity, 'sort_order' => $order];
                $order += 10;
            }
        }
        return $result;
    }

    private function resolveEntity(string $type, int $id): ?array
    {
        return match ($type) {
            'project' => $this->project($id),
            'experience' => $this->experience($id),
            'organization' => $this->simpleEntity('organizations', $id),
            'technology' => $this->simpleEntity('technologies', $id, true),
            'education' => $this->simpleEntity('education', $id, true),
            'certification' => $this->simpleEntity('certifications', $id, true),
            'publication' => $this->publication($id),
            'evaluation_board' => $this->simpleEntity('evaluation_boards', $id, true),
            'project-technologies' => $this->projectIsPublished($id) ? ['items' => $this->projectTechnologies($id)] : null,
            'project-media' => $this->projectIsPublished($id) ? ['project_name' => $this->projectName($id), 'items' => $this->projectMedia($id)] : null,
            'project-links' => $this->projectIsPublished($id) ? ['items' => $this->projectLinks($id)] : null,
            'project-relations' => $this->projectIsPublished($id) ? $this->projectRelationsSource($id) : null,
            'project-experiences' => $this->projectIsPublished($id) ? ['items' => $this->projectExperiences($id)] : null,
            default => null,
        };
    }

    private function simpleEntity(string $table, int $id, bool $visibleOnly = false): ?array
    {
        $visibility = $visibleOnly ? ' AND is_visible=1' : '';
        $stmt = $this->database->pdo()->prepare("SELECT * FROM `{$table}` WHERE id=?{$visibility} LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function experience(int $id): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT e.*,o.name organization,o.slug organization_slug,o.image_url organization_image_url
             FROM experiences e JOIN organizations o ON o.id=e.organization_id WHERE e.id=? AND e.is_visible=1 LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['highlights'] = $this->decodeJson($row['highlights'] ?? null);
        $row['technologies'] = $this->experienceTechnologies($id);
        return $row;
    }

    private function project(int $id): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT p.*,o.name organization,o.slug organization_slug,o.image_url organization_image_url
             FROM projects p LEFT JOIN organizations o ON o.id=p.organization_id WHERE p.id=? AND p.status='published' LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['technologies'] = $this->projectTechnologies($id);
        $row['media'] = $this->projectMedia($id);
        $row['links'] = $this->projectLinks($id);
        $row['related_experiences'] = $this->projectExperiences($id);
        $row['related_contexts'] = $this->projectRelations($id);
        $sections = $this->database->pdo()->prepare("SELECT * FROM project_sections WHERE project_id=? AND is_visible=1 ORDER BY sort_order,id");
        $sections->execute([$id]);
        $row['sections'] = $sections->fetchAll();
        return $row;
    }

    private function publication(int $id): ?array
    {
        $row = $this->simpleEntity('publications', $id, true);
        if (!$row) return null;

        $media = $this->database->pdo()->prepare(
            "SELECT * FROM publication_media
             WHERE publication_id=? AND is_visible=1
               AND (NULLIF(TRIM(media_url), '') IS NOT NULL OR NULLIF(TRIM(embed_url), '') IS NOT NULL)
             ORDER BY sort_order,id"
        );
        $media->execute([$id]);
        $row['media'] = $media->fetchAll();

        $sections = $this->database->pdo()->prepare(
            "SELECT id,eyebrow,title,body,items FROM publication_sections
             WHERE publication_id=? AND is_visible=1
             ORDER BY sort_order,id"
        );
        $sections->execute([$id]);
        $row['sections'] = array_map(function (array $section): array {
            $section['items'] = $this->decodeJson($section['items'] ?? null);
            return $section;
        }, $sections->fetchAll());

        $projects = $this->database->pdo()->prepare(
            "SELECT p.id,p.slug,p.name,p.period_label,p.context,pp.relation_label
             FROM publication_projects pp
             JOIN projects p ON p.id=pp.project_id
             WHERE pp.publication_id=? AND pp.is_visible=1 AND p.status='published'
             ORDER BY pp.sort_order,p.sort_order,p.id"
        );
        $projects->execute([$id]);
        $row['related_projects'] = $projects->fetchAll();

        return $row;
    }

    private function experienceTechnologies(int $id): array
    {
        $stmt = $this->database->pdo()->prepare("SELECT t.* FROM technologies t JOIN experience_technology et ON et.technology_id=t.id WHERE et.experience_id=? AND t.is_visible=1 ORDER BY et.sort_order,t.sort_order,t.name");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    private function projectIsPublished(int $id): bool
    {
        $stmt = $this->database->pdo()->prepare("SELECT 1 FROM projects WHERE id=? AND status='published' LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    private function projectTechnologies(int $id): array
    {
        $stmt = $this->database->pdo()->prepare("SELECT t.* FROM technologies t JOIN project_technology pt ON pt.technology_id=t.id JOIN projects p ON p.id=pt.project_id AND p.status='published' WHERE pt.project_id=? AND t.is_visible=1 ORDER BY pt.sort_order,t.sort_order,t.name");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    private function projectName(int $id): string
    {
        $stmt = $this->database->pdo()->prepare("SELECT name FROM projects WHERE id=? AND status='published' LIMIT 1");
        $stmt->execute([$id]);
        return (string) ($stmt->fetchColumn() ?: 'Projeto');
    }

    private function projectMedia(int $id): array
    {
        $stmt = $this->database->pdo()->prepare("SELECT pm.* FROM project_media pm JOIN projects p ON p.id=pm.project_id AND p.status='published' WHERE pm.project_id=? AND pm.is_visible=1 AND NULLIF(TRIM(pm.media_url), '') IS NOT NULL ORDER BY pm.sort_order,pm.id");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    private function projectLinks(int $id): array
    {
        $stmt = $this->database->pdo()->prepare("SELECT pl.* FROM project_links pl JOIN projects p ON p.id=pl.project_id AND p.status='published' WHERE pl.project_id=? AND pl.is_visible=1 AND NULLIF(TRIM(pl.url), '') IS NOT NULL ORDER BY pl.sort_order,pl.id");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    private function projectRelationsSource(int $id): ?array
    {
        $items = $this->projectRelations($id);
        return $items ? ['items' => $items] : null;
    }

    private function projectRelations(int $id): array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT pr.entity_type,pr.entity_id,pr.relation_label,pr.sort_order
             FROM project_relations pr
             JOIN projects p ON p.id=pr.project_id AND p.status='published'
             WHERE pr.project_id=? AND pr.is_visible=1
             ORDER BY pr.sort_order,pr.id"
        );
        $stmt->execute([$id]);

        $result = [];
        foreach ($stmt->fetchAll() as $relation) {
            $entityType = (string) ($relation['entity_type'] ?? '');
            if (!in_array($entityType, ['experience', 'education', 'certification'], true)) {
                continue;
            }

            $entity = $this->resolveEntity($entityType, (int) $relation['entity_id']);
            if (!$entity || (array_key_exists('is_visible', $entity) && (int) $entity['is_visible'] !== 1)) {
                continue;
            }

            $entity['_relation_type'] = $entityType;
            $entity['_relation_label'] = $relation['relation_label'] ?: null;
            $entity['_relation_sort_order'] = (int) $relation['sort_order'];
            $result[] = $entity;
        }

        return $result;
    }

    private function projectExperiences(int $id): array
    {
        $stmt = $this->database->pdo()->prepare("SELECT e.id FROM experiences e JOIN project_experiences pe ON pe.experience_id=e.id JOIN projects p ON p.id=pe.project_id AND p.status='published' WHERE pe.project_id=? AND e.is_visible=1 ORDER BY pe.sort_order,e.start_date DESC");
        $stmt->execute([$id]);
        $result=[];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $experienceId) {
            $entity=$this->experience((int)$experienceId);
            if ($entity) $result[]=$entity;
        }
        return $result;
    }
}
