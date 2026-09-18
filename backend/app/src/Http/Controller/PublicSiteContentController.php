<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PublicSiteContentController
{
    public function __construct(private readonly Database $database) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $pdo = $this->database->pdo();
        $navigation = $pdo->query(
            "SELECT n.id,n.location,n.label,n.page_id,n.url,n.is_external,n.sort_order,
                    CASE WHEN n.page_id IS NOT NULL THEN CASE WHEN p.slug='home' THEN '/' ELSE CONCAT('/',p.slug) END ELSE n.url END resolved_url
             FROM site_navigation n LEFT JOIN pages p ON p.id=n.page_id
             WHERE n.is_visible=1 AND (n.page_id IS NULL OR p.status='published')
             ORDER BY CASE n.location WHEN 'header' THEN 0 WHEN 'footer' THEN 1 ELSE 2 END,n.sort_order,n.id"
        )->fetchAll();
        $settings = $pdo->query("SELECT * FROM site_settings WHERE id=1 LIMIT 1")->fetch() ?: [];
        return JsonResponse::write($response, ['navigation' => $navigation, 'settings' => $settings]);
    }
}
