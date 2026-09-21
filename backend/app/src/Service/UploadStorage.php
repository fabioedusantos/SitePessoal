<?php

declare(strict_types=1);

namespace App\Service;

use App\Admin\ResourceRegistry;
use App\Config\Env;
use App\Infrastructure\Database;
use GdImage;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class UploadStorage
{
    private const IMAGE_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const DOCUMENT_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * Presets correspond to the visual contracts used by the public site.
     * Fixed presets always generate the exact target dimensions. Free presets
     * preserve the source ratio and only reduce oversized images.
     */
    private const IMAGE_PRESETS = [
        'showcase' => [
            'mode' => 'cover',
            'width' => 1200,
            'height' => 320,
            'min_width' => 1200,
            'min_height' => 320,
            'label' => '1200 × 320 px (15:4)',
        ],
        'academic-logo' => [
            'mode' => 'contain',
            'width' => 520,
            'height' => 150,
            'min_width' => 520,
            'min_height' => 150,
            'label' => '520 × 150 px (52:15)',
        ],
        'open-graph' => [
            'mode' => 'cover',
            'width' => 1200,
            'height' => 630,
            'min_width' => 1200,
            'min_height' => 630,
            'label' => '1200 × 630 px',
        ],
        'brand-logo' => [
            'mode' => 'fit',
            'max_width' => 2065,
            'max_height' => 1000,
        ],
        'free' => [
            'mode' => 'fit',
            'max_width' => 2400,
            'max_height' => 2400,
        ],
    ];

    public function __construct(private readonly Database $database)
    {
    }

    /** @return array<int,array<string,int|string|null>> */
    public function listImages(string $preset): array
    {
        $preset = strtolower(trim($preset));
        $config = self::IMAGE_PRESETS[$preset] ?? null;
        if ($config === null) {
            throw new RuntimeException('Padrão de imagem inválido. Atualize a página do painel e tente novamente.');
        }

        // Instalações anteriores já podem possuir uploads válidos sem registro na
        // biblioteca. A sincronização é idempotente e transforma esses arquivos
        // referenciados em itens reutilizáveis sem exigir novo upload.
        $this->synchronizeReferencedImages();

        $rows = $this->database->pdo()->query(
            "SELECT id, original_name, mime_type, size_bytes, storage_path, alt_text, width, height, created_at
             FROM media
             WHERE mime_type LIKE 'image/%'
             ORDER BY created_at DESC, id DESC"
        )->fetchAll();

        $targetWidth = isset($config['width']) ? (int) $config['width'] : null;
        $targetHeight = isset($config['height']) ? (int) $config['height'] : null;
        $seenPaths = [];
        $items = [];

        foreach ($rows as $row) {
            $storagePath = $this->managedPath((string) ($row['storage_path'] ?? ''));
            if ($storagePath === null || isset($seenPaths[$storagePath])) {
                continue;
            }

            $path = $this->filesystemPath($storagePath);
            if ($path === null || !is_file($path)) {
                continue;
            }

            $width = (int) ($row['width'] ?? 0);
            $height = (int) ($row['height'] ?? 0);
            if ($targetWidth !== null && $targetHeight !== null && ($width !== $targetWidth || $height !== $targetHeight)) {
                continue;
            }

            $seenPaths[$storagePath] = true;
            $items[] = [
                'id' => (int) $row['id'],
                'url' => $this->publicUrl($storagePath),
                'original_name' => (string) ($row['original_name'] ?: basename($storagePath)),
                'alt_text' => $row['alt_text'] !== null ? (string) $row['alt_text'] : null,
                'width' => $width ?: null,
                'height' => $height ?: null,
                'size_bytes' => (int) ($row['size_bytes'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $items;
    }

    /** @return array{url:string,reused:bool} */
    public function storeImage(UploadedFileInterface $file, string $scope, string $preset = 'free'): array
    {
        $contents = $this->readUploadedFile($file, true);
        $mime = $this->detectMime($contents);
        if (!isset(self::IMAGE_MIME_TYPES[$mime])) {
            throw new RuntimeException('Formato não permitido. Envie uma imagem JPG, PNG ou WebP.');
        }

        [$width, $height] = $this->validateImage($contents);
        $preset = strtolower(trim($preset));
        $config = self::IMAGE_PRESETS[$preset] ?? null;
        if ($config === null) {
            throw new RuntimeException('Padrão de imagem inválido. Atualize a página do painel e tente novamente.');
        }

        // Garante que uploads de versões anteriores também participem da
        // detecção de duplicidade mesmo que a biblioteca ainda não tenha sido aberta.
        $this->synchronizeReferencedImages();

        $minWidth = (int) ($config['min_width'] ?? 0);
        $minHeight = (int) ($config['min_height'] ?? 0);
        if (($minWidth > 0 && $width < $minWidth) || ($minHeight > 0 && $height < $minHeight)) {
            throw new RuntimeException(sprintf(
                'A imagem é menor do que o padrão deste campo. Envie uma imagem com pelo menos %s.',
                (string) ($config['label'] ?? sprintf('%d × %d px', $minWidth, $minHeight))
            ));
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            throw new RuntimeException('O processamento de imagens não está disponível no servidor. Reconstrua o container do backend com a extensão GD.');
        }

        $source = @imagecreatefromstring($contents);
        if (!$source instanceof GdImage) {
            throw new RuntimeException('Não foi possível processar a imagem enviada.');
        }

        try {
            $processed = $this->processImage($source, $width, $height, $config);
            $relativeDirectory = $this->relativeDirectory($scope);
            $directory = $this->ensureDirectory($relativeDirectory);
            $filename = bin2hex(random_bytes(18)) . '.webp';
            $target = $directory . '/' . $filename;
            $quality = min(100, max(60, (int) Env::get('UPLOAD_WEBP_QUALITY', 88)));

            if (!@imagewebp($processed, $target, $quality)) {
                throw new RuntimeException('Não foi possível gravar a imagem processada.');
            }
            @chmod($target, 0664);

            $storagePath = '/uploads/' . $relativeDirectory . '/' . $filename;
            $processedWidth = imagesx($processed);
            $processedHeight = imagesy($processed);

            // Mesmo que o usuário envie novamente o mesmo arquivo, não criamos
            // outra cópia física quando a versão processada já existe.
            $duplicatePath = $this->findDuplicateImage($target, $processedWidth, $processedHeight);
            if ($duplicatePath !== null) {
                @unlink($target);
                $this->removeEmptyParents(dirname($target));
                return ['url' => $this->publicUrl($duplicatePath), 'reused' => true];
            }

            $this->registerMedia(
                $storagePath,
                (string) ($file->getClientFilename() ?: $filename),
                'image/webp',
                (int) (filesize($target) ?: 0),
                $processedWidth,
                $processedHeight
            );

            return ['url' => $this->publicUrl($storagePath), 'reused' => false];
        } finally {
            if (isset($processed) && $processed instanceof GdImage && $processed !== $source) {
                imagedestroy($processed);
            }
            imagedestroy($source);
        }
    }

    public function storeDocument(UploadedFileInterface $file, string $scope): string
    {
        $contents = $this->readUploadedFile($file, false);
        $mime = $this->detectMime($contents);
        $extension = self::DOCUMENT_MIME_TYPES[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Formato não permitido. Envie JPG, PNG, WebP ou PDF.');
        }

        if (isset(self::IMAGE_MIME_TYPES[$mime])) {
            $this->validateImage($contents);
        }

        $relativeDirectory = $this->relativeDirectory($scope);
        $directory = $this->ensureDirectory($relativeDirectory);
        $filename = bin2hex(random_bytes(18)) . '.' . $extension;
        $target = $directory . '/' . $filename;

        if (file_put_contents($target, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível gravar o arquivo enviado.');
        }
        @chmod($target, 0664);

        return '/uploads/' . $relativeDirectory . '/' . $filename;
    }

    private function readUploadedFile(UploadedFileInterface $file, bool $imageOnly): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException($imageOnly ? 'Não foi possível receber a imagem enviada.' : 'Não foi possível receber o arquivo enviado.');
        }

        $maxMb = max(1, (int) Env::get('UPLOAD_MAX_IMAGE_MB', 12));
        $maxBytes = $maxMb * 1024 * 1024;
        $size = (int) ($file->getSize() ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException(sprintf('O arquivo deve ter no máximo %d MB.', $maxMb));
        }

        $stream = $file->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $contents = $stream->getContents();
        if ($contents === '' || strlen($contents) > $maxBytes) {
            throw new RuntimeException('O arquivo é inválido ou excede o limite permitido.');
        }

        return $contents;
    }

    private function detectMime(string $contents): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return (string) $finfo->buffer($contents);
    }

    /** @return array{0:int,1:int} */
    private function validateImage(string $contents): array
    {
        $imageInfo = @getimagesizefromstring($contents);
        if (!$imageInfo || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw new RuntimeException('O arquivo enviado não é uma imagem válida.');
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        if ($width > 10000 || $height > 10000 || ($width * $height) > 40_000_000) {
            throw new RuntimeException('A resolução da imagem é maior do que o permitido.');
        }

        return [$width, $height];
    }

    /** @param array<string,int|string> $config */
    private function processImage(GdImage $source, int $sourceWidth, int $sourceHeight, array $config): GdImage
    {
        return match ($config['mode']) {
            'cover' => $this->coverImage($source, $sourceWidth, $sourceHeight, (int) $config['width'], (int) $config['height']),
            'contain' => $this->containImage($source, $sourceWidth, $sourceHeight, (int) $config['width'], (int) $config['height']),
            default => $this->fitImage($source, $sourceWidth, $sourceHeight, (int) $config['max_width'], (int) $config['max_height']),
        };
    }

    private function coverImage(GdImage $source, int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight): GdImage
    {
        $targetRatio = $targetWidth / $targetHeight;
        $sourceRatio = $sourceWidth / $sourceHeight;
        $cropX = 0.0;
        $cropY = 0.0;
        $cropWidth = (float) $sourceWidth;
        $cropHeight = (float) $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            $cropWidth = $sourceHeight * $targetRatio;
            $cropX = ($sourceWidth - $cropWidth) / 2;
        } elseif ($sourceRatio < $targetRatio) {
            $cropHeight = $sourceWidth / $targetRatio;
            $cropY = ($sourceHeight - $cropHeight) / 2;
        }

        $canvas = $this->transparentCanvas($targetWidth, $targetHeight);
        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            (int) round($cropX),
            (int) round($cropY),
            $targetWidth,
            $targetHeight,
            (int) round($cropWidth),
            (int) round($cropHeight)
        );

        return $canvas;
    }

    private function containImage(GdImage $source, int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight): GdImage
    {
        $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $renderWidth = max(1, (int) round($sourceWidth * $scale));
        $renderHeight = max(1, (int) round($sourceHeight * $scale));
        $destinationX = (int) floor(($targetWidth - $renderWidth) / 2);
        $destinationY = (int) floor(($targetHeight - $renderHeight) / 2);

        $canvas = $this->transparentCanvas($targetWidth, $targetHeight);
        imagecopyresampled($canvas, $source, $destinationX, $destinationY, 0, 0, $renderWidth, $renderHeight, $sourceWidth, $sourceHeight);

        return $canvas;
    }

    private function fitImage(GdImage $source, int $sourceWidth, int $sourceHeight, int $maxWidth, int $maxHeight): GdImage
    {
        $scale = min(1.0, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $canvas = $this->transparentCanvas($targetWidth, $targetHeight);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        return $canvas;
    }

    private function transparentCanvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        if (!$canvas instanceof GdImage) {
            throw new RuntimeException('Não foi possível preparar a imagem para processamento.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        return $canvas;
    }

    private function relativeDirectory(string $scope): string
    {
        $safeScope = strtolower(trim($scope));
        $safeScope = preg_replace('/[^a-z0-9-]+/', '-', $safeScope) ?: 'general';
        $safeScope = trim($safeScope, '-');
        if ($safeScope === '') {
            $safeScope = 'general';
        }

        return sprintf('%s/%s/%s', $safeScope, date('Y'), date('m'));
    }

    private function ensureDirectory(string $relativeDirectory): string
    {
        $basePath = rtrim((string) Env::get('UPLOADS_PATH', dirname(__DIR__, 2) . '/public/uploads'), '/');
        $directory = $basePath . '/' . $relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o diretório para upload.');
        }

        return $directory;
    }

    public function deleteIfUnused(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || !$this->isManagedUpload($url) || $this->isReferenced($url)) {
            return false;
        }

        $path = $this->filesystemPath($url);
        $deletedFile = false;
        if ($path !== null && is_file($path)) {
            $deletedFile = @unlink($path);
            if ($deletedFile) {
                $this->removeEmptyParents(dirname($path));
            }
        }

        $deletedMedia = $this->deleteMediaRecord($url);
        return $deletedFile || $deletedMedia;
    }

    public function isManagedUpload(?string $url): bool
    {
        return $this->managedPath((string) $url) !== null;
    }

    private function isReferenced(string $url): bool
    {
        $managedPath = $this->managedPath($url);
        if ($managedPath === null) {
            return false;
        }

        $pdo = $this->database->pdo();
        foreach (ResourceRegistry::uploadReferences() as $reference) {
            $table = $reference['table'];
            $field = $reference['field'];
            $stmt = $pdo->prepare(
                "SELECT 1 FROM `{$table}` WHERE `{$field}` = ? OR `{$field}` = ? OR `{$field}` LIKE ? LIMIT 1"
            );
            $stmt->execute([$url, $managedPath, '%' . $managedPath]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        }

        return false;
    }

    private function filesystemPath(string $url): ?string
    {
        $managedPath = $this->managedPath($url);
        if ($managedPath === null) {
            return null;
        }

        $relative = rawurldecode(substr($managedPath, strlen('/uploads/')));
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '..')) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', str_replace('\\', '/', $relative)), static fn ($segment) => $segment !== ''));
        if (!$segments || array_filter($segments, static fn ($segment) => !preg_match('/^[A-Za-z0-9._-]+$/', $segment))) {
            return null;
        }

        $basePath = rtrim((string) Env::get('UPLOADS_PATH', dirname(__DIR__, 2) . '/public/uploads'), '/');
        return $basePath . '/' . implode('/', $segments);
    }

    private function managedPath(string $url): ?string
    {
        $path = parse_url(trim($url), PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/uploads/')) {
            return null;
        }

        return $path;
    }

    private function publicUrl(string $storagePath): string
    {
        $managedPath = $this->managedPath($storagePath);
        if ($managedPath === null) {
            return trim($storagePath);
        }

        return $managedPath;
    }

    private function registerMedia(string $storagePath, string $originalName, string $mimeType, int $sizeBytes, int $width, int $height): void
    {
        $managedPath = $this->managedPath($storagePath);
        if ($managedPath === null) {
            return;
        }

        $pdo = $this->database->pdo();
        $check = $pdo->prepare('SELECT id, filename, original_name, mime_type, size_bytes, storage_path, width, height FROM media WHERE storage_path = ? OR storage_path LIKE ? LIMIT 1');
        $check->execute([$managedPath, '%' . $managedPath]);
        $existing = $check->fetch();
        if ($existing) {
            $normalizedOriginalName = (string) ($existing['original_name'] ?: mb_substr(basename($originalName), 0, 255));
            $normalizedSize = max(0, $sizeBytes);
            $needsUpdate = (string) ($existing['filename'] ?? '') !== basename($managedPath)
                || $normalizedOriginalName !== (string) ($existing['original_name'] ?? '')
                || (string) ($existing['mime_type'] ?? '') !== $mimeType
                || (int) ($existing['size_bytes'] ?? 0) !== $normalizedSize
                || (string) ($existing['storage_path'] ?? '') !== $managedPath
                || (int) ($existing['width'] ?? 0) !== $width
                || (int) ($existing['height'] ?? 0) !== $height;

            if ($needsUpdate) {
                $update = $pdo->prepare(
                    'UPDATE media SET filename = ?, original_name = ?, mime_type = ?, size_bytes = ?, storage_path = ?, width = ?, height = ? WHERE id = ?'
                );
                $update->execute([
                    basename($managedPath),
                    $normalizedOriginalName,
                    $mimeType,
                    $normalizedSize,
                    $managedPath,
                    $width,
                    $height,
                    (int) $existing['id'],
                ]);
            }
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO media (filename, original_name, mime_type, size_bytes, storage_path, width, height) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            basename($managedPath),
            mb_substr(basename($originalName), 0, 255),
            $mimeType,
            max(0, $sizeBytes),
            $managedPath,
            $width,
            $height,
        ]);
    }

    private function synchronizeReferencedImages(): void
    {
        $pdo = $this->database->pdo();

        foreach (ResourceRegistry::uploadReferences() as $reference) {
            $table = $reference['table'];
            $field = $reference['field'];
            $stmt = $pdo->query(
                "SELECT DISTINCT `{$field}` AS upload_url FROM `{$table}` WHERE `{$field}` IS NOT NULL AND `{$field}` <> ''"
            );

            foreach ($stmt->fetchAll() as $row) {
                $url = trim((string) ($row['upload_url'] ?? ''));
                $managedPath = $this->managedPath($url);
                if ($managedPath === null) {
                    continue;
                }

                $path = $this->filesystemPath($managedPath);
                if ($path === null || !is_file($path)) {
                    continue;
                }

                $imageInfo = @getimagesize($path);
                if (!$imageInfo || empty($imageInfo[0]) || empty($imageInfo[1])) {
                    continue;
                }

                $mime = (string) ($imageInfo['mime'] ?? '');
                if (!isset(self::IMAGE_MIME_TYPES[$mime])) {
                    continue;
                }

                $this->registerMedia(
                    $managedPath,
                    basename($managedPath),
                    $mime,
                    (int) (filesize($path) ?: 0),
                    (int) $imageInfo[0],
                    (int) $imageInfo[1]
                );
            }
        }
    }

    private function findDuplicateImage(string $candidatePath, int $width, int $height): ?string
    {
        $candidateSize = (int) (filesize($candidatePath) ?: 0);
        $candidateHash = hash_file('sha256', $candidatePath);
        if ($candidateHash === false) {
            return null;
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT storage_path, size_bytes FROM media WHERE mime_type='image/webp' AND width=? AND height=? ORDER BY id DESC"
        );
        $stmt->execute([$width, $height]);

        foreach ($stmt->fetchAll() as $row) {
            if ((int) ($row['size_bytes'] ?? 0) !== $candidateSize) {
                continue;
            }

            $managedPath = $this->managedPath((string) ($row['storage_path'] ?? ''));
            if ($managedPath === null) {
                continue;
            }
            $existingPath = $this->filesystemPath($managedPath);
            if ($existingPath === null || !is_file($existingPath)) {
                continue;
            }

            $existingHash = hash_file('sha256', $existingPath);
            if ($existingHash !== false && hash_equals($existingHash, $candidateHash)) {
                return $managedPath;
            }
        }

        return null;
    }

    private function deleteMediaRecord(string $url): bool
    {
        $managedPath = $this->managedPath($url);
        if ($managedPath === null) {
            return false;
        }

        $stmt = $this->database->pdo()->prepare(
            'DELETE FROM media WHERE storage_path = ? OR storage_path = ? OR storage_path LIKE ?'
        );
        $stmt->execute([$url, $managedPath, '%' . $managedPath]);
        return $stmt->rowCount() > 0;
    }

    private function removeEmptyParents(string $directory): void
    {
        $basePath = rtrim((string) Env::get('UPLOADS_PATH', dirname(__DIR__, 2) . '/public/uploads'), '/');
        $current = $directory;

        while ($current !== $basePath && str_starts_with($current, $basePath . '/')) {
            $items = @scandir($current);
            if ($items === false || count($items) > 2) {
                break;
            }
            if (!@rmdir($current)) {
                break;
            }
            $current = dirname($current);
        }
    }
}
