<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Config\Env;
use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct()
    {
        $host = (string) Env::get('DB_HOST', '127.0.0.1');
        $port = (string) Env::get('DB_PORT', '3306');
        $database = (string) Env::get('MARIADB_DATABASE', 'fbsantos');
        $username = (string) Env::get('MARIADB_USER', 'fbsantos');
        $password = (string) Env::get('MARIADB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
