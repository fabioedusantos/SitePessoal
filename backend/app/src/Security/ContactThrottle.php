<?php

declare(strict_types=1);

namespace App\Security;

use App\Config\Env;
use App\Infrastructure\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;

final class ContactThrottle
{
    private PDO $pdo;

    public function __construct(Database $database)
    {
        $this->pdo = $database->pdo();
    }

    public function consume(string $key): bool
    {
        $maxAttempts = max(1, (int) Env::get('CONTACT_RATE_LIMIT_MAX', 5));
        $windowSeconds = max(60, (int) Env::get('CONTACT_RATE_LIMIT_WINDOW_SECONDS', 900));
        $blockSeconds = max(60, (int) Env::get('CONTACT_RATE_LIMIT_BLOCK_SECONDS', 1800));
        $hash = hash('sha256', $key);
        $now = new DateTimeImmutable();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT attempts, window_started_at, blocked_until FROM contact_rate_limits WHERE key_hash = ? FOR UPDATE'
            );
            $stmt->execute([$hash]);
            $row = $stmt->fetch();

            if (!$row) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO contact_rate_limits (key_hash, attempts, window_started_at) VALUES (?, 1, ?)'
                );
                $insert->execute([$hash, $now->format('Y-m-d H:i:s')]);
                $this->pdo->commit();
                return true;
            }

            $blockedUntil = !empty($row['blocked_until']) ? new DateTimeImmutable((string) $row['blocked_until']) : null;
            if ($blockedUntil && $blockedUntil > $now) {
                $this->pdo->commit();
                return false;
            }

            $windowStarted = new DateTimeImmutable((string) $row['window_started_at']);
            $windowExpired = $windowStarted->modify("+{$windowSeconds} seconds") <= $now;

            if ($windowExpired) {
                $update = $this->pdo->prepare(
                    'UPDATE contact_rate_limits SET attempts = 1, window_started_at = ?, blocked_until = NULL WHERE key_hash = ?'
                );
                $update->execute([$now->format('Y-m-d H:i:s'), $hash]);
                $this->pdo->commit();
                return true;
            }

            $attempts = (int) $row['attempts'];
            if ($attempts >= $maxAttempts) {
                $blocked = $now->add(new DateInterval('PT' . $blockSeconds . 'S'));
                $update = $this->pdo->prepare(
                    'UPDATE contact_rate_limits SET blocked_until = ? WHERE key_hash = ?'
                );
                $update->execute([$blocked->format('Y-m-d H:i:s'), $hash]);
                $this->pdo->commit();
                return false;
            }

            $update = $this->pdo->prepare(
                'UPDATE contact_rate_limits SET attempts = attempts + 1, blocked_until = NULL WHERE key_hash = ?'
            );
            $update->execute([$hash]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
