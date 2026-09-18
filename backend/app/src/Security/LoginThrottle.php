<?php

declare(strict_types=1);

namespace App\Security;

use App\Config\Env;
use App\Infrastructure\Database;
use DateTimeImmutable;
use PDO;

final class LoginThrottle
{
    private PDO $pdo;

    public function __construct(Database $database)
    {
        $this->pdo = $database->pdo();
    }

    public function assertAllowed(string $key): void
    {
        $hash = hash('sha256', $key);
        $stmt = $this->pdo->prepare(
            'SELECT attempts, window_started_at, blocked_until
             FROM login_rate_limits
             WHERE key_hash = ?
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row || !$row['blocked_until']) {
            return;
        }

        if (new DateTimeImmutable((string) $row['blocked_until']) > new DateTimeImmutable()) {
            throw new \RuntimeException('Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.');
        }
    }

    public function registerFailure(string $key): void
    {
        $windowMinutes = max(1, (int) Env::get('LOGIN_RATE_LIMIT_WINDOW_MINUTES', 15));
        $maxAttempts = max(2, (int) Env::get('LOGIN_RATE_LIMIT_MAX', 8));
        $blockMinutes = max(1, (int) Env::get('LOGIN_RATE_LIMIT_BLOCK_MINUTES', 30));
        $hash = hash('sha256', $key);
        $now = new DateTimeImmutable();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT attempts, window_started_at
                 FROM login_rate_limits
                 WHERE key_hash = ?
                 FOR UPDATE'
            );
            $stmt->execute([$hash]);
            $row = $stmt->fetch();

            if (!$row) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO login_rate_limits (key_hash, attempts, window_started_at)
                     VALUES (?, 1, ?)'
                );
                $insert->execute([$hash, $now->format('Y-m-d H:i:s')]);
                $this->pdo->commit();
                return;
            }

            $windowStart = new DateTimeImmutable((string) $row['window_started_at']);
            $windowExpired = $windowStart->modify("+{$windowMinutes} minutes") <= $now;
            $attempts = $windowExpired ? 1 : ((int) $row['attempts'] + 1);
            $newWindow = $windowExpired ? $now : $windowStart;
            $blockedUntil = $attempts >= $maxAttempts
                ? $now->modify("+{$blockMinutes} minutes")->format('Y-m-d H:i:s')
                : null;

            $update = $this->pdo->prepare(
                'UPDATE login_rate_limits
                 SET attempts = ?, window_started_at = ?, blocked_until = ?
                 WHERE key_hash = ?'
            );
            $update->execute([
                $attempts,
                $newWindow->format('Y-m-d H:i:s'),
                $blockedUntil,
                $hash,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function clear(string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_rate_limits WHERE key_hash = ?');
        $stmt->execute([hash('sha256', $key)]);
    }
}
