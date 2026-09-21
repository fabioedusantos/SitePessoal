<?php

declare(strict_types=1);

use App\Admin\ResourceRegistry;
use App\Infrastructure\Database;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$appRoot = dirname(__DIR__);
$envFile = $appRoot . '/.env';
if (is_file($envFile)) {
    Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
}

$pdo = (new Database())->pdo();
$total = 0;
foreach (ResourceRegistry::uploadReferences() as $reference) {
    $table = $reference['table'];
    $field = $reference['field'];
    $rows = $pdo->query("SELECT id, `{$field}` AS upload_url FROM `{$table}` WHERE `{$field}` IS NOT NULL AND `{$field}` <> ''")->fetchAll();
    $update = $pdo->prepare("UPDATE `{$table}` SET `{$field}`=? WHERE id=?");

    foreach ($rows as $row) {
        $value = trim((string) ($row['upload_url'] ?? ''));
        $path = parse_url($value, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/uploads/') || $value === $path) {
            continue;
        }
        $update->execute([$path, (int) $row['id']]);
        $total += $update->rowCount();
    }
}

echo "URLs de uploads normalizadas: {$total}\n";
