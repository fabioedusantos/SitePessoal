<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Service\UploadStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class AdminUploadController
{
    public function __construct(private readonly UploadStorage $uploads)
    {
    }

    public function images(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $preset = (string) ($query['preset'] ?? 'free');

        try {
            $items = $this->uploads->listImages($preset);
        } catch (RuntimeException $e) {
            return JsonResponse::write($response, ['message' => $e->getMessage()], 422);
        }

        return JsonResponse::write($response, ['data' => $items]);
    }

    public function image(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file) {
            return JsonResponse::write($response, ['message' => 'Selecione uma imagem para enviar.'], 422);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $scope = (string) ($body['scope'] ?? 'site');
        $preset = (string) ($body['preset'] ?? 'free');

        try {
            $upload = $this->uploads->storeImage($file, $scope, $preset);
        } catch (RuntimeException $e) {
            return JsonResponse::write($response, ['message' => $e->getMessage()], 422);
        }

        return JsonResponse::write($response, $upload, 201);
    }


    public function file(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file) {
            return JsonResponse::write($response, ['message' => 'Selecione um arquivo para enviar.'], 422);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $scope = (string) ($body['scope'] ?? 'documents');

        try {
            $url = $this->uploads->storeDocument($file, $scope);
        } catch (RuntimeException $e) {
            return JsonResponse::write($response, ['message' => $e->getMessage()], 422);
        }

        return JsonResponse::write($response, ['url' => $url], 201);
    }

    public function deleteFile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->deleteManagedUpload($request, $response, 'Arquivo gerenciado inválido.');
    }

    public function deleteImage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->deleteManagedUpload($request, $response, 'Imagem gerenciada inválida.');
    }

    private function deleteManagedUpload(ServerRequestInterface $request, ResponseInterface $response, string $message): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $url = trim((string) ($body['url'] ?? ''));
        if ($url === '' || !$this->uploads->isManagedUpload($url)) {
            return JsonResponse::write($response, ['message' => $message], 422);
        }

        return JsonResponse::write($response, [
            'deleted' => $this->uploads->deleteIfUnused($url),
        ]);
    }


}
