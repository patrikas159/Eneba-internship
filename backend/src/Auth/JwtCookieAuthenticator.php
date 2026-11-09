<?php

declare(strict_types=1);

namespace App\Auth;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Stateless authentication: every request carrying the auth cookie is authenticated from the JWT.
 * An invalid/expired cookie is ignored (request continues anonymously); protected routes then
 * answer 401 JSON through start().
 */
final class JwtCookieAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly JwtTokenService $tokens,
        private readonly UserRepository $users,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return '' !== (string) $request->cookies->get(AuthCookie::NAME, '');
    }

    public function authenticate(Request $request): Passport
    {
        $userId = $this->tokens->userIdFrom((string) $request->cookies->get(AuthCookie::NAME));
        if (null === $userId) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired session.');
        }

        return new SelfValidatingPassport(new UserBadge((string) $userId, function (string $id) {
            return $this->users->find((int) $id) ?? throw new UserNotFoundException();
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // let the request reach its controller
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null; // treat as anonymous; access_control decides whether that's allowed
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'unauthorized', 'message' => 'Sign in to continue.'], 401);
    }
}
