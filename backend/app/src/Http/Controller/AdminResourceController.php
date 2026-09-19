<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Admin\ResourceRegistry;
use App\Http\JsonResponse;
use App\Infrastructure\Database;
use App\Service\UploadStorage;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminResourceController
{
    public function __construct(
        private readonly Database $database,
        private readonly UploadStorage $uploads,
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $this->config($args['resource']);
        if (!$config) {
            return JsonResponse::write($response, ['message' => 'Recurso inválido.'], 404);
        }

        $table = $config['table'];
        $orderBy = $config['order_by'] ?? 'sort_order, id DESC';
        $rows = $this->database->pdo()->query("SELECT * FROM `{$table}` ORDER BY {$orderBy}")->fetchAll();

        return JsonResponse::write($response, ['data' => $rows]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $this->config($args['resource']);
        if (!$config) {
            return JsonResponse::write($response, ['message' => 'Recurso inválido.'], 404);
        }

        $stmt = $this->database->pdo()->prepare("SELECT * FROM `{$config['table']}` WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $args['id']]);
        $row = $stmt->fetch();

        return $row
            ? JsonResponse::write($response, ['data' => $row])
            : JsonResponse::write($response, ['message' => 'Registro não encontrado.'], 404);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $this->config($args['resource']);
        if (!$config) {
            return JsonResponse::write($response, ['message' => 'Recurso inválido.'], 404);
        }

        $data = $this->sanitize((array) $request->getParsedBody(), $config);
        $error = $this->validate($data, $config);
        if ($error) {
            return JsonResponse::write($response, $error, 422);
        }

        $fields = array_keys($data);
        $columns = implode(', ', array_map(fn ($f) => "`{$f}`", $fields));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));

        $pdo = $this->database->pdo();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO `{$config['table']}` ({$columns}) VALUES ({$placeholders})"
            );
            $stmt->execute(array_values($data));
            $id = (int) $pdo->lastInsertId();
            if ($args['resource'] === 'projects') {
                $this->syncProjectPage($id);
            }
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $this->databaseError($response, $exception, $config);
        }

        return JsonResponse::write($response, ['id' => $id], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $this->config($args['resource']);
        if (!$config) {
            return JsonResponse::write($response, ['message' => 'Recurso inválido.'], 404);
        }

        $data = $this->sanitize((array) $request->getParsedBody(), $config);
        if (!$data) {
            return JsonResponse::write($response, ['message' => 'Nenhum campo válido informado.'], 422);
        }

        $existing = $this->findRow($config['table'], (int) $args['id']);
        if (!$existing) {
            return JsonResponse::write($response, ['message' => 'Registro não encontrado.'], 404);
        }

        $error = $this->validate(array_replace($existing, $data), $config);
        if ($error) {
            return JsonResponse::write($response, $error, 422);
        }

        $set = implode(', ', array_map(fn ($f) => "`{$f}` = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = (int) $args['id'];

        $pdo = $this->database->pdo();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "UPDATE `{$config['table']}` SET {$set}, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $stmt->execute($values);
            if ($args['resource'] === 'projects') {
                $this->syncProjectPage((int) $args['id']);
            }
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $this->databaseError($response, $exception, $config);
        }

        $this->cleanupReplacedUploads($config, $existing, $data);

        return JsonResponse::write($response, ['updated' => true]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $this->config($args['resource']);
        if (!$config) {
            return JsonResponse::write($response, ['message' => 'Recurso inválido.'], 404);
        }

        $existing = $this->findRow($config['table'], (int) $args['id']);
        if (!$existing) {
            return JsonResponse::write($response, ['message' => 'Registro não encontrado.'], 404);
        }

        $dependentUploads = $this->dependentUploadUrls($config, (int) $args['id']);
        $pageBuilderUploads = [];
        if ($args['resource'] === 'pages') {
            $pageBuilderUploads = $this->pageBuilderUploadUrls((int) $args['id']);
        } elseif ($args['resource'] === 'projects') {
            $pageBuilderUploads = $this->projectPageUploadUrls((int) $args['id']);
        }

        $stmt = $this->database->pdo()->prepare("DELETE FROM `{$config['table']}` WHERE id = ?");
        $stmt->execute([(int) $args['id']]);

        if ($stmt->rowCount() > 0) {
            $this->deletePageBuilderReferences($args['resource'], (int) $args['id']);
            if ($args['resource'] === 'projects') {
                $deletePage = $this->database->pdo()->prepare("DELETE FROM pages WHERE entity_type='project' AND entity_id=?");
                $deletePage->execute([(int) $args['id']]);
            }
            $this->cleanupDeletedUploads($config, $existing);
            foreach (array_values(array_unique([...$dependentUploads, ...$pageBuilderUploads])) as $url) {
                $this->uploads->deleteIfUnused($url);
            }
        }

        return JsonResponse::write($response, ['deleted' => $stmt->rowCount() > 0]);
    }

    private function config(string $resource): ?array
    {
        return ResourceRegistry::get($resource);
    }

    private function findRow(string $table, int $id): ?array
    {
        $stmt = $this->database->pdo()->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function cleanupReplacedUploads(array $config, array $existing, array $data): void
    {
        foreach ($config['upload_fields'] ?? [] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $old = trim((string) ($existing[$field] ?? ''));
            $new = trim((string) ($data[$field] ?? ''));
            if ($old !== '' && $old !== $new) {
                $this->uploads->deleteIfUnused($old);
            }
        }
    }

    private function cleanupDeletedUploads(array $config, array $existing): void
    {
        foreach ($config['upload_fields'] ?? [] as $field) {
            $url = trim((string) ($existing[$field] ?? ''));
            if ($url !== '') {
                $this->uploads->deleteIfUnused($url);
            }
        }
    }

    private function deletePageBuilderReferences(string $resource, int $id): void
    {
        $entityTypes = [
            'projects' => 'project',
            'experiences' => 'experience',
            'organizations' => 'organization',
            'technologies' => 'technology',
            'education' => 'education',
            'certifications' => 'certification',
            'publications' => 'publication',
            'evaluation-boards' => 'evaluation_board',
        ];

        $entityType = $entityTypes[$resource] ?? null;
        if (!$entityType) {
            return;
        }

        $stmt = $this->database->pdo()->prepare(
            'DELETE FROM page_section_items WHERE entity_type = ? AND entity_id = ?'
        );
        $stmt->execute([$entityType, $id]);

        if (in_array($entityType, ['experience', 'education', 'certification'], true)) {
            $relations = $this->database->pdo()->prepare(
                'DELETE FROM project_relations WHERE entity_type = ? AND entity_id = ?'
            );
            $relations->execute([$entityType, $id]);
        }
    }

    private function syncProjectPage(int $projectId): void
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id=? LIMIT 1');
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
        if (!$project) {
            return;
        }

        $slug = 'projetos/' . $project['slug'];
        $status = $project['status'] === 'published' ? 'published' : 'draft';
        $pageStmt = $pdo->prepare("SELECT id FROM pages WHERE entity_type='project' AND entity_id=? LIMIT 1");
        $pageStmt->execute([$projectId]);
        $pageId = (int) ($pageStmt->fetchColumn() ?: 0);

        if ($pageId > 0) {
            $update = $pdo->prepare('UPDATE pages SET slug=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $update->execute([$slug, $status, $pageId]);
            return;
        }

        $insert = $pdo->prepare("INSERT INTO pages (title,slug,status,page_kind,entity_type,entity_id,seo_title,seo_description,canonical_url,og_image_url,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $insert->execute([
            $project['name'], $slug, $status, 'project', 'project', $projectId,
            $project['name'] . ' | Projeto | Fábio Santos',
            mb_substr((string) ($project['context'] ?: ('Case ' . $project['name'] . ' na trajetória profissional de Fábio Santos.')), 0, 500),
            'https://fbsantos.com.br/' . $slug, $project['cover_image_url'], 1000 + (int) $project['sort_order'],
        ]);
        $pageId = (int) $pdo->lastInsertId();

        $sections = [
            ['project-hero','Abertura',null,null,10,'default','wide'],
            ['project-story','Case','Case','O projeto em detalhes',20,'light','normal'],
            ['technologies','Tecnologias','Tecnologias','Tecnologias utilizadas',30,'default','wide'],
            ['gallery','Galeria','Galeria','O produto em tela.',40,'dark','wide'],
            ['links','Links','Links','Links e recursos',50,'light','normal'],
            ['related-contexts','Trajetória relacionada','Trajetória','Trajetória relacionada',60,'default','wide'],
            ['cta','Contato','Projeto e contexto','Quer conversar sobre este tipo de desafio?',70,'brand','normal'],
        ];
        $insertSection = $pdo->prepare('INSERT INTO page_sections (page_id,section_type,internal_name,eyebrow,title,lead,cta_label,cta_url,theme,width_mode,settings,sort_order,is_visible) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)');
        foreach ($sections as [$type,$name,$eyebrow,$title,$order,$theme,$width]) {
            $settings = in_array($type, ['project-hero','project-story','technologies','gallery','links','related-contexts'], true)
                ? json_encode(['entity_type' => match ($type) {
                    'technologies' => 'project-technologies', 'gallery' => 'project-media', 'links' => 'project-links', 'related-contexts' => 'project-relations', default => 'project',
                }, 'entity_id' => $projectId], JSON_UNESCAPED_SLASHES)
                : '{}';
            $insertSection->execute([$pageId,$type,$name,$eyebrow,$title,$type==='cta' ? 'Contexto técnico fica melhor em uma boa conversa.' : null,$type==='cta' ? 'Entrar em contato' : null,$type==='cta' ? '/contato' : null,$theme,$width,$settings,$order]);
        }
    }

    private function projectPageUploadUrls(int $projectId): array
    {
        $stmt = $this->database->pdo()->prepare("SELECT id FROM pages WHERE entity_type='project' AND entity_id=? LIMIT 1");
        $stmt->execute([$projectId]);
        $pageId = (int) ($stmt->fetchColumn() ?: 0);
        return $pageId > 0 ? $this->pageBuilderUploadUrls($pageId) : [];
    }

    private function pageBuilderUploadUrls(int $pageId): array
    {
        $pdo = $this->database->pdo();
        $urls = [];

        $page = $pdo->prepare("SELECT og_image_url FROM pages WHERE id=? AND og_image_url IS NOT NULL AND og_image_url<>''");
        $page->execute([$pageId]);
        foreach ($page->fetchAll(PDO::FETCH_COLUMN) as $url) {
            $urls[] = (string) $url;
        }

        $sections = $pdo->prepare("SELECT image_url FROM page_sections WHERE page_id=? AND image_url IS NOT NULL AND image_url<>''");
        $sections->execute([$pageId]);
        foreach ($sections->fetchAll(PDO::FETCH_COLUMN) as $url) {
            $urls[] = (string) $url;
        }

        $items = $pdo->prepare(
            "SELECT psi.image_url FROM page_section_items psi JOIN page_sections ps ON ps.id=psi.section_id
             WHERE ps.page_id=? AND psi.image_url IS NOT NULL AND psi.image_url<>''"
        );
        $items->execute([$pageId]);
        foreach ($items->fetchAll(PDO::FETCH_COLUMN) as $url) {
            $urls[] = (string) $url;
        }

        return array_values(array_unique(array_filter(array_map('trim', $urls))));
    }

    private function dependentUploadUrls(array $config, int $id): array
    {
        $urls = [];
        foreach ($config['dependent_uploads'] ?? [] as $dependency) {
            $table = $dependency['table'];
            $field = $dependency['field'];
            $foreignKey = $dependency['foreign_key'];
            $stmt = $this->database->pdo()->prepare(
                "SELECT `{$field}` FROM `{$table}` WHERE `{$foreignKey}` = ? AND `{$field}` IS NOT NULL AND `{$field}` <> ''"
            );
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $url) {
                $value = trim((string) $url);
                if ($value !== '') {
                    $urls[] = $value;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function sanitize(array $input, array $config): array
    {
        $data = [];
        foreach ($config['fields'] as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];

            if (in_array($field, ['highlights', 'items'], true) && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if ($value === '') {
                $value = $field === 'sort_order' ? 0 : null;
            }

            $data[$field] = $value;
        }

        return $data;
    }

    private function validate(array $data, array $config): ?array
    {
        foreach ($config['required'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $message = 'Preencha ' . mb_strtolower($this->fieldLabel($field)) . '.';
                return ['message' => $message, 'errors' => [$field => $message]];
            }
        }

        foreach ($config['allowed_values'] ?? [] as $field => $allowedValues) {
            if (isset($data[$field]) && !in_array((string) $data[$field], $allowedValues, true)) {
                $message = 'Selecione um valor válido para ' . mb_strtolower($this->fieldLabel($field)) . '.';
                return ['message' => $message, 'errors' => [$field => $message]];
            }
        }

        foreach ($data as $field => $value) {
            if ($value === null) {
                continue;
            }
            if ($field === 'slug' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/', (string) $value)) {
                $message = 'O slug deve usar apenas letras minúsculas, números, hífens e barras entre segmentos.';
                return ['message' => $message, 'errors' => ['slug' => $message]];
            }
            if (($field === 'url' || str_ends_with($field, '_url')) && !$this->isSafeUrl((string) $value)) {
                $message = 'Informe uma URL válida em ' . mb_strtolower($this->fieldLabel($field)) . '.';
                return ['message' => $message, 'errors' => [$field => $message]];
            }
            if (in_array($field, ['background_color', 'text_color', 'accent_color'], true)
                && !preg_match('/^#[0-9a-f]{6}$/i', (string) $value)) {
                $message = 'Informe uma cor hexadecimal válida.';
                return ['message' => $message, 'errors' => [$field => $message]];
            }
            if ($field === 'settings' && !$this->settingsAreSafe($value)) {
                return ['message' => 'As configurações contêm uma URL não permitida.', 'errors' => ['settings' => 'Revise as URLs informadas nas configurações.']];
            }
        }

        if ($config['table'] === 'page_section_items' && ($data['item_type'] ?? '') === 'manual') {
            $sectionId = (int) ($data['section_id'] ?? 0);
            $stmt = $this->database->pdo()->prepare('SELECT section_type FROM page_sections WHERE id = ? LIMIT 1');
            $stmt->execute([$sectionId]);
            if ($stmt->fetchColumn() === 'cards') {
                $settings = $data['settings'] ?? '{}';
                $settings = is_array($settings) ? $settings : json_decode((string) $settings, true);
                $mode = is_array($settings) ? ($settings['content_mode'] ?? null) : null;
                $hasText = trim((string) ($data['body'] ?? '')) !== '';
                $hasImage = trim((string) ($data['image_url'] ?? '')) !== '';

                if ($hasText && $hasImage || ($mode === 'text' && $hasImage) || ($mode === 'image' && $hasText)) {
                    $message = 'O card deve conter texto ou imagem, nunca os dois.';
                    return ['message' => $message, 'errors' => ['body' => $message, 'image_url' => $message]];
                }
                if ($mode !== null && !in_array($mode, ['text', 'image'], true)) {
                    $message = 'Selecione um tipo de conteúdo válido para o card.';
                    return ['message' => $message, 'errors' => ['settings' => $message]];
                }
                if ($mode === 'image' && !$hasImage) {
                    $message = 'Selecione uma imagem para o card.';
                    return ['message' => $message, 'errors' => ['image_url' => $message]];
                }
            }
        }

        if (!empty($data['start_date']) && !empty($data['end_date']) && (string) $data['end_date'] < (string) $data['start_date']) {
            $message = 'A data final não pode ser anterior à data inicial.';
            return ['message' => $message, 'errors' => ['end_date' => $message]];
        }

        if (isset($data['workload_hours']) && $data['workload_hours'] !== null && (int) $data['workload_hours'] <= 0) {
            $message = 'A carga horária deve ser maior que zero.';
            return ['message' => $message, 'errors' => ['workload_hours' => $message]];
        }

        return null;
    }

    private function databaseError(ResponseInterface $response, PDOException $exception, array $config): ResponseInterface
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($driverCode === 1062) {
            if (in_array('slug', $config['fields'], true)) {
                $message = 'Já existe outro registro usando este slug.';
                return JsonResponse::write($response, ['message' => $message, 'errors' => ['slug' => $message]], 409);
            }
            return JsonResponse::write($response, ['message' => 'Este relacionamento já foi cadastrado.'], 409);
        }

        if ($driverCode === 1452) {
            return JsonResponse::write($response, ['message' => 'Um dos conteúdos relacionados não existe mais. Atualize a página e selecione novamente.'], 422);
        }

        if ($driverCode === 1048 && preg_match("/Column '([^']+)' cannot be null/i", $exception->getMessage(), $matches)) {
            $field = (string) ($matches[1] ?? '');
            $message = 'Preencha ' . mb_strtolower($this->fieldLabel($field)) . '.';
            return JsonResponse::write($response, ['message' => $message, 'errors' => [$field => $message]], 422);
        }

        return JsonResponse::write($response, ['message' => 'O servidor não conseguiu concluir o salvamento. Tente novamente.'], 500);
    }

    private function fieldLabel(string $field): string
    {
        return [
            'organization_id' => 'Organização',
            'experience_id' => 'Experiência',
            'project_id' => 'Projeto',
            'publication_id' => 'Publicação',
            'page_id' => 'Página',
            'section_id' => 'Seção',
            'technology_id' => 'Tecnologia',
            'slug' => 'Slug',
            'role_title' => 'Cargo',
            'start_date' => 'Data inicial',
            'end_date' => 'Data final',
            'name' => 'Nome',
            'title' => 'Título',
            'category' => 'Categoria',
            'institution' => 'Instituição',
            'course' => 'Curso',
            'issuer' => 'Emissor',
            'kind' => 'Tipo',
            'event_date' => 'Data',
            'label' => 'Nome',
            'url' => 'URL',
            'media_url' => 'Mídia / URL',
            'alt_text' => 'Texto alternativo',
            'media_type' => 'Tipo de mídia',
            'entity_type' => 'Tipo de conteúdo relacionado',
            'entity_id' => 'Conteúdo relacionado',
            'section_type' => 'Tipo de seção',
            'item_type' => 'Tipo de item',
            'location' => 'Local',
            'status' => 'Status',
            'workload_hours' => 'Carga horária',
        ][$field] ?? str_replace('_', ' ', ucfirst($field));
    }

    private function isSafeUrl(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return true;
        }
        if (str_starts_with($value, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (in_array($scheme, ['mailto', 'tel'], true)) {
            return !preg_match('/[\r\n]/', $value);
        }
        return in_array($scheme, ['http', 'https'], true) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function settingsAreSafe(mixed $value): bool
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return trim($value) === '' || trim($value) === '{}';
            }
            $value = $decoded;
        }
        if (!is_array($value)) {
            return false;
        }

        $walk = function (array $items) use (&$walk): bool {
            foreach ($items as $key => $item) {
                if (is_array($item) && !$walk($item)) {
                    return false;
                }
                if (is_string($key) && (str_ends_with($key, '_url') || $key === 'url')
                    && is_string($item) && !$this->isSafeUrl($item)) {
                    return false;
                }
            }
            return true;
        };

        return $walk($value);
    }
}
