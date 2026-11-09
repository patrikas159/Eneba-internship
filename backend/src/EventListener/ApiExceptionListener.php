<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Every error under /api is JSON `{error, message}` (unknown routes, wrong methods, crashes),
 * never Symfony's HTML error page. Security exceptions are left to the firewall (priority > 0
 * would pre-empt the authenticator's JSON 401 entry point, so this runs late).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final class ApiExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $e = $event->getThrowable();
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $codes = [400 => 'bad_request', 401 => 'unauthorized', 403 => 'forbidden', 404 => 'not_found', 405 => 'method_not_allowed'];
            $body = ['error' => $codes[$status] ?? 'http_error', 'message' => $status < 500 ? ($e->getMessage() ?: 'Request failed.') : 'Something went wrong.'];
            $event->setResponse(new JsonResponse($body, $status, $e->getHeaders()));

            return;
        }

        $this->logger->error('Unhandled API exception', ['exception' => $e]);
        $event->setResponse(new JsonResponse(['error' => 'internal_error', 'message' => 'Something went wrong.'], 500));
    }
}
