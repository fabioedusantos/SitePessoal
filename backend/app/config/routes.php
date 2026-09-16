<?php

declare(strict_types=1);

use App\Http\Controller\AdminAnalyticsController;
use App\Http\Controller\AdminMailController;
use App\Http\Controller\AdminDashboardController;
use App\Http\Controller\AdminProfileController;
use App\Http\Controller\AdminResourceController;
use App\Http\Controller\AdminUploadController;
use App\Http\Controller\AuthController;
use App\Http\Controller\PublicContactController;
use App\Http\Controller\PublicCareerContentController;
use App\Http\Controller\PublicExperienceController;
use App\Http\Controller\PublicProjectController;
use App\Http\Controller\PublicPageController;
use App\Http\Controller\PublicSiteContentController;
use App\Middleware\AdminAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $app->get('/', fn ($request, $response) =>
        \App\Http\JsonResponse::write($response, [
            'service' => 'FBSantos API',
            'status' => 'ok',
        ])
    );

    $app->get('/health', fn ($request, $response) =>
        \App\Http\JsonResponse::write($response, ['status' => 'ok'])
    );

    $app->group('/api/v1/public', function (RouteCollectorProxy $group): void {
        $group->get('/site-content', PublicSiteContentController::class);
        $group->get('/pages', [PublicPageController::class, 'index']);
        $group->get('/page/{slug:.*}', [PublicPageController::class, 'show']);
        $group->get('/experiences', [PublicExperienceController::class, 'list']);
        $group->get('/projects', [PublicProjectController::class, 'list']);
        $group->get('/projects/{slug}', [PublicProjectController::class, 'show']);
        $group->get('/publications', [PublicCareerContentController::class, 'publications']);
        $group->get('/evaluation-boards', [PublicCareerContentController::class, 'evaluationBoards']);
        $group->post('/contact', PublicContactController::class);
    });

    $app->post('/api/v1/auth/login', [AuthController::class, 'login']);

    $app->group('/api/v1/admin', function (RouteCollectorProxy $group): void {
        $group->get('/me', function ($request, $response) {
            $claims = $request->getAttribute('admin');
            return \App\Http\JsonResponse::write($response, [
                'authenticated' => true,
                'email' => $claims->email ?? null,
                'csrf_token' => $claims->csrf ?? null,
            ]);
        });
        $group->post('/logout', [AuthController::class, 'logout']);
        $group->get('/dashboard', AdminDashboardController::class);
        $group->get('/analytics', AdminAnalyticsController::class);
        $group->get('/mail-usage', AdminMailController::class);
        $group->get('/profile', [AdminProfileController::class, 'show']);
        $group->put('/profile', [AdminProfileController::class, 'update']);
        $group->get('/uploads/images', [AdminUploadController::class, 'images']);
        $group->post('/uploads/image', [AdminUploadController::class, 'image']);
        $group->delete('/uploads/image', [AdminUploadController::class, 'deleteImage']);
        $group->post('/uploads/file', [AdminUploadController::class, 'file']);
        $group->delete('/uploads/file', [AdminUploadController::class, 'deleteFile']);
        $group->get('/{resource}', [AdminResourceController::class, 'list']);
        $group->post('/{resource}', [AdminResourceController::class, 'create']);
        $group->get('/{resource}/{id:[0-9]+}', [AdminResourceController::class, 'show']);
        $group->put('/{resource}/{id:[0-9]+}', [AdminResourceController::class, 'update']);
        $group->delete('/{resource}/{id:[0-9]+}', [AdminResourceController::class, 'delete']);
    })->add(AdminAuthMiddleware::class);
};
