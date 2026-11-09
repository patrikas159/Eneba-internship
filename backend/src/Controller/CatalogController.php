<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalog\CatalogReadModel;
use App\Catalog\ProductListQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public, read-only catalog endpoints: Store listing, filter facets, search autocomplete, product page.
 */
final class CatalogController
{
    public function __construct(private readonly CatalogReadModel $catalog)
    {
    }

    #[Route('/api/products', name: 'api_products_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $query = ProductListQuery::fromRequest($request);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'invalid_query', 'message' => $e->getMessage()], 400);
        }

        return new JsonResponse($this->catalog->listProducts($query));
    }

    #[Route('/api/products/filters', name: 'api_products_filters', methods: ['GET'], priority: 10)]
    public function filters(): JsonResponse
    {
        return new JsonResponse($this->catalog->filters());
    }

    #[Route('/api/search/suggest', name: 'api_search_suggest', methods: ['GET'])]
    public function suggest(Request $request): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query->get('q', '')), 0, 100);
        if (mb_strlen($q) < 2) {
            return new JsonResponse(['items' => []]);
        }

        return new JsonResponse(['items' => $this->catalog->suggest($q)]);
    }

    #[Route('/api/products/{slug}', name: 'api_products_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function show(string $slug): JsonResponse
    {
        $product = $this->catalog->productBySlug($slug);
        if (null === $product) {
            return new JsonResponse(['error' => 'not_found', 'message' => 'Product not found'], 404);
        }

        return new JsonResponse($product);
    }
}
