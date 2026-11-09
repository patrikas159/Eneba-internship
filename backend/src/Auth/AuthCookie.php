<?php

declare(strict_types=1);

namespace App\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Builds the httpOnly session cookie. JavaScript never sees the token (XSS can't steal it),
 * and SameSite=Lax keeps it off cross-site POSTs (CSRF) while the SPA and API share an origin.
 */
final class AuthCookie
{
    public const NAME = 'eneba_auth';

    public function __construct(
        private readonly JwtTokenService $tokens,
        #[Autowire('%auth.cookie_secure%')] private readonly bool $secure,
    ) {
    }

    public function forToken(string $token): Cookie
    {
        return $this->build($token, time() + $this->tokens->ttlSeconds());
    }

    public function cleared(): Cookie
    {
        return $this->build('', 1);
    }

    private function build(string $value, int $expires): Cookie
    {
        return Cookie::create(self::NAME)
            ->withValue($value)
            ->withExpires($expires)
            ->withPath('/')
            ->withSecure($this->secure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
