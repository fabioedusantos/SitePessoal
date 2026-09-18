<?php

declare(strict_types=1);

namespace App\Security;

use App\Config\Env;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

final class JwtService
{
    private const DEFAULT_ISSUER = 'fbsantos-api';
    private const DEFAULT_AUDIENCE = 'fbsantos-panel';

    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $ttlSeconds,
    ) {
    }

    public static function fromEnv(): self
    {
        $secret = (string) Env::get('JWT_SECRET', '');
        $minimumLength = strtolower((string) Env::get('APP_ENV', 'development')) === 'production' ? 48 : 32;
        if (strlen($secret) < $minimumLength) {
            throw new RuntimeException("JWT_SECRET deve possuir pelo menos {$minimumLength} bytes.");
        }

        $ttl = max(900, min(28800, (int) Env::get('JWT_TTL_SECONDS', 14400)));
        return new self(
            $secret,
            trim((string) Env::get('JWT_ISSUER', self::DEFAULT_ISSUER)) ?: self::DEFAULT_ISSUER,
            trim((string) Env::get('JWT_AUDIENCE', self::DEFAULT_AUDIENCE)) ?: self::DEFAULT_AUDIENCE,
            $ttl,
        );
    }

    public function issue(int $userId, string $email, string $csrf, string $credentialFingerprint): string
    {
        $now = time();

        return JWT::encode([
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $userId,
            'email' => $email,
            'csrf' => $csrf,
            'authv' => $credentialFingerprint,
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttlSeconds,
        ], $this->secret, 'HS256');
    }

    public function decode(string $token): object
    {
        $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
        if (($claims->iss ?? null) !== $this->issuer || ($claims->aud ?? null) !== $this->audience) {
            throw new RuntimeException('Token emitido para contexto inválido.');
        }
        if (!isset($claims->sub, $claims->email, $claims->csrf, $claims->authv) || !ctype_digit((string) $claims->sub)) {
            throw new RuntimeException('Token incompleto.');
        }

        return $claims;
    }

    public function credentialFingerprint(string $passwordHash): string
    {
        return hash_hmac('sha256', $passwordHash, $this->secret);
    }

    public function ttlSeconds(): int
    {
        return $this->ttlSeconds;
    }
}
