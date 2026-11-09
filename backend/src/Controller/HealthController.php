<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness + database check. Used by the Docker Compose healthcheck
 * (and later by Render) to know the API can actually reach Postgres.
 */
final class HealthController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'error', 'database' => 'unreachable'], 503);
        }

        return new JsonResponse(['status' => 'ok', 'database' => 'ok']);
    }
}
