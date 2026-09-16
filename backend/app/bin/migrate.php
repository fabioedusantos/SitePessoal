<?php

declare(strict_types=1);

use App\Infrastructure\Database;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$appRoot = dirname(__DIR__);
$moduleRoot = dirname($appRoot);

$envFile = $appRoot . '/.env';
if (is_file($envFile)) {
    Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
}

$migrationsPath = (string) ($_ENV['MIGRATIONS_PATH'] ?? getenv('MIGRATIONS_PATH') ?: $moduleRoot . '/database/migrations');
if (!is_dir($migrationsPath)) {
    throw new RuntimeException("Diretório de migrations não encontrado: {$migrationsPath}");
}

$db = new Database();
$pdo = $db->pdo();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$files = glob(rtrim($migrationsPath, '/') . '/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $filename = basename($file);
    $check = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE filename = ?');
    $check->execute([$filename]);

    if ((int) $check->fetchColumn() > 0) {
        echo "skip {$filename}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Não foi possível ler {$filename}");
    }

    $pdo->exec($sql);
    $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
    $stmt->execute([$filename]);
    echo "ok   {$filename}\n";
}
