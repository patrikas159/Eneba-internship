<?php

declare(strict_types=1);

namespace App\Auth;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Issues and verifies the HS256 session token stored in the auth cookie.
 * The token only carries the user id ("sub"); the user row is reloaded on every request.
 */
final class JwtTokenService
{
    private const ALGORITHM = 'HS256';
    private const ISSUER = 'eneba-marketplace';

    public function __construct(
        #[Autowire('%env(JWT_SECRET)%')] private readonly string $secret,
        #[Autowire('%auth.token_ttl%')] private readonly int $ttlSeconds,
    ) {
    }

    public function issue(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss' => self::ISSUER,
            'sub' => (string) $user->getId(),
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ], $this->key(), self::ALGORITHM);
    }

    /**
     * @return int|null user id when the token is authentic and not expired, null otherwise
     */
    public function userIdFrom(string $token): ?int
    {
        try {
            $claims = JWT::decode($token, new Key($this->key(), self::ALGORITHM));
        } catch (\Throwable) {
            return null; // bad signature, expired, malformed: all treated as "not signed in"
        }

        if (($claims->iss ?? null) !== self::ISSUER || !isset($claims->sub) || !ctype_digit((string) $claims->sub)) {
            return null;
        }

        return (int) $claims->sub;
    }

    public function ttlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    private function key(): string
    {
        if (\strlen($this->secret) < 32) {
            throw new \LogicException('JWT_SECRET must be set to at least 32 characters (see backend/.env).');
        }

        return $this->secret;
    }
}
