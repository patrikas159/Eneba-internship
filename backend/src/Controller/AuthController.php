<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthCookie;
use App\Auth\JwtTokenService;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Register / sign in / sign out / current user. A successful register or login sets the
 * httpOnly JWT cookie; the SPA only ever sees the user profile, never the token.
 */
final class AuthController
{
    private const PASSWORD_MIN = 8;
    private const PASSWORD_MAX = 128;

    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly JwtTokenService $tokens,
        private readonly AuthCookie $cookie,
    ) {
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $body = $this->jsonBody($request);
        $email = mb_strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $displayName = trim((string) ($body['displayName'] ?? ''));

        $errors = [];
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (mb_strlen($password) < self::PASSWORD_MIN || mb_strlen($password) > self::PASSWORD_MAX) {
            $errors['password'] = \sprintf('Password must be %d-%d characters.', self::PASSWORD_MIN, self::PASSWORD_MAX);
        }
        if ('' === $displayName) {
            $displayName = mb_substr(strstr($email, '@', true) ?: $email, 0, 80);
        } elseif (mb_strlen($displayName) > 80) {
            $errors['displayName'] = 'Display name must be at most 80 characters.';
        }
        if ([] !== $errors) {
            return new JsonResponse(['error' => 'validation_failed', 'message' => 'Check the highlighted fields.', 'fields' => $errors], 422);
        }

        if (null !== $this->users->findOneBy(['email' => $email])) {
            return $this->emailTaken();
        }

        $user = new User($email, '', $displayName);
        $user->setPasswordHash($this->hasher->hashPassword($user, $password));
        try {
            $this->em->persist($user);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->emailTaken(); // lost a race with a concurrent signup for the same email
        }

        return $this->signedIn($user, 201);
    }

    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $body = $this->jsonBody($request);
        $email = mb_strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        $user = '' === $email ? null : $this->users->findOneBy(['email' => $email]);
        if (null === $user) {
            // Spend the same bcrypt time as a real check so response timing doesn't reveal which emails exist.
            $this->hasher->hashPassword(new User('timing@example.invalid', '', 'timing'), $password);

            return $this->invalidCredentials();
        }
        if (!$this->hasher->isPasswordValid($user, $password)) {
            return $this->invalidCredentials();
        }
        if ($this->hasher->needsRehash($user)) {
            $user->setPasswordHash($this->hasher->hashPassword($user, $password));
            $this->em->flush();
        }

        return $this->signedIn($user, 200);
    }

    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(): Response
    {
        $response = new Response(null, 204);
        $response->headers->setCookie($this->cookie->cleared());

        return $response;
    }

    /** Protected by access_control: anonymous callers get 401 from the authenticator's entry point. */
    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(['user' => self::userJson($user)]);
    }

    /** @return array{id:int,email:string,displayName:string,createdAt:string} */
    public static function userJson(User $user): array
    {
        return [
            'id' => (int) $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    private function signedIn(User $user, int $status): JsonResponse
    {
        $response = new JsonResponse(['user' => self::userJson($user)], $status);
        $response->headers->setCookie($this->cookie->forToken($this->tokens->issue($user)));

        return $response;
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            return []; // malformed JSON falls through to field validation / invalid credentials
        }
    }

    private function emailTaken(): JsonResponse
    {
        return new JsonResponse(['error' => 'email_taken', 'message' => 'An account with this email already exists.', 'fields' => ['email' => 'An account with this email already exists.']], 409);
    }

    private function invalidCredentials(): JsonResponse
    {
        return new JsonResponse(['error' => 'invalid_credentials', 'message' => 'Email or password is incorrect.'], 401);
    }
}
