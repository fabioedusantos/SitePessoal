<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminProfileController
{
    private const FIELDS = [
        'full_name',
        'professional_title',
        'headline',
        'summary',
        'about_short',
        'location',
        'email',
        'phone',
    ];

    public function __construct(private readonly Database $database)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $row = $this->database->pdo()->query('SELECT * FROM site_profile WHERE id = 1')->fetch();

        return $row
            ? JsonResponse::write($response, ['data' => $row])
            : JsonResponse::write($response, ['message' => 'Apresentação ainda não cadastrada.'], 404);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = (array) $request->getParsedBody();
        $data = [];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = trim((string) $input[$field]);
            }
        }

        foreach (['full_name', 'professional_title', 'headline', 'summary'] as $required) {
            if (!isset($data[$required]) || $data[$required] === '') {
                return JsonResponse::write(
                    $response,
                    ['message' => "O campo {$required} é obrigatório."],
                    422
                );
            }
        }

        $set = implode(', ', array_map(fn (string $field) => "`{$field}` = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = 1;

        $stmt = $this->database->pdo()->prepare(
            "UPDATE site_profile SET {$set}, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $stmt->execute($values);

        return JsonResponse::write($response, ['updated' => true]);
    }
}
