<?php

declare(strict_types=1);

use App\Infrastructure\Database;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$appRoot = dirname(__DIR__);
$envFile = $appRoot . '/.env';
if (is_file($envFile)) {
    Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
}

$email = mb_strtolower(trim((string) ($_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: '')));
$passwordHash = trim((string) ($_ENV['ADMIN_PASSWORD_HASH'] ?? getenv('ADMIN_PASSWORD_HASH') ?: ''));
$displayName = trim((string) ($_ENV['ADMIN_DISPLAY_NAME'] ?? getenv('ADMIN_DISPLAY_NAME') ?: 'Administrador'));
$replace = in_array('--replace', $argv, true);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Defina ADMIN_EMAIL com um e-mail válido.');
}
if ($passwordHash === '') {
    throw new RuntimeException('Defina ADMIN_PASSWORD_HASH. Gere-o com php bin/hash-admin-password.php.');
}
$passwordInfo = password_get_info($passwordHash);
if (($passwordInfo['algoName'] ?? '') !== 'bcrypt' || (int) ($passwordInfo['options']['cost'] ?? 0) < 12) {
    throw new RuntimeException('ADMIN_PASSWORD_HASH deve ser bcrypt com custo mínimo 12.');
}
if ($displayName === '' || mb_strlen($displayName) > 120) {
    throw new RuntimeException('ADMIN_DISPLAY_NAME inválido.');
}

$pdo = (new Database())->pdo();
$stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email=? LIMIT 1');
$stmt->execute([$email]);
$id = $stmt->fetchColumn();

if ($id !== false) {
    if (!$replace) {
        echo "Administrador já existe. Nenhuma alteração realizada. Use --replace para substituir nome e senha.\n";
        exit(0);
    }
    $update = $pdo->prepare('UPDATE admin_users SET password_hash=?, display_name=?, is_active=1 WHERE id=?');
    $update->execute([$passwordHash, $displayName, (int) $id]);
    echo "Administrador atualizado.\n";
    exit(0);
}

$insert = $pdo->prepare('INSERT INTO admin_users (email,password_hash,display_name,is_active) VALUES (?,?,?,1)');
$insert->execute([$email, $passwordHash, $displayName]);
echo "Administrador criado.\n";
