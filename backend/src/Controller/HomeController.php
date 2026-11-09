<?php

declare(strict_types=1);

namespace App\Controller;

use App\Home\HomeLayout;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public homepage layout: the frontend renders whatever blocks come back, in order.
 * Each block is filled by its own provider (see App\Home\Provider), nothing is hardcoded client-side.
 */
final class HomeController
{
    public function __construct(private readonly HomeLayout $layout)
    {
    }

    #[Route('/api/home', name: 'api_home', methods: ['GET'])]
    public function home(): JsonResponse
    {
        $response = new JsonResponse(['blocks' => $this->layout->build()]);
        $response->setPublic();
        $response->setMaxAge(60);

        return $response;
    }
}
