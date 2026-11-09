<?php

declare(strict_types=1);

namespace App\Catalog;

use Symfony\Component\HttpFoundation\Request;

/**
 * Validated Store listing parameters (GET /api/products).
 */
final readonly class ProductListQuery
{
    public const SORTS = ['relevance', 'price_asc', 'price_desc', 'newest', 'name', 'discount'];
    public const MAX_LIMIT = 60;

    public function __construct(
        public ?string $q = null,
        /** @var list<string> */
        public array $platforms = [],
        /** @var list<string> */
        public array $regions = [],
        public ?string $genre = null,
        public ?int $minPriceCents = null,
        public ?int $maxPriceCents = null,
        public bool $inStockOnly = false,
        public string $sort = 'relevance',
        public int $page = 1,
        public int $limit = 24,
    ) {
    }

    /**
     * @throws \InvalidArgumentException on an invalid parameter (mapped to HTTP 400)
     */
    public static function fromRequest(Request $request): self
    {
        $q = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', '' === $q ? 'newest' : 'relevance');
        if (!\in_array($sort, self::SORTS, true)) {
            throw new \InvalidArgumentException(\sprintf('sort must be one of: %s', implode(', ', self::SORTS)));
        }

        $min = self::intOrNull($request, 'minPrice');
        $max = self::intOrNull($request, 'maxPrice');
        if (null !== $min && null !== $max && $min > $max) {
            throw new \InvalidArgumentException('minPrice must not exceed maxPrice');
        }

        $page = self::intOrNull($request, 'page') ?? 1;
        $limit = self::intOrNull($request, 'limit') ?? 24;
        if ($page < 1 || $limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException(\sprintf('page must be >= 1 and limit between 1 and %d', self::MAX_LIMIT));
        }

        $genre = trim((string) $request->query->get('genre', ''));

        return new self(
            q: '' === $q ? null : mb_substr($q, 0, 100),
            platforms: self::list($request, 'platform'),
            regions: self::list($request, 'region'),
            genre: '' === $genre ? null : $genre,
            minPriceCents: $min,
            maxPriceCents: $max,
            inStockOnly: $request->query->getBoolean('inStock'),
            // "relevance" without a search term means nothing; fall back to newest.
            sort: 'relevance' === $sort && '' === $q ? 'newest' : $sort,
            page: $page,
            limit: $limit,
        );
    }

    private static function intOrNull(Request $request, string $key): ?int
    {
        $raw = $request->query->get($key);
        if (null === $raw || '' === $raw) {
            return null;
        }
        if (!ctype_digit((string) $raw)) {
            throw new \InvalidArgumentException("{$key} must be a non-negative integer");
        }

        return (int) $raw;
    }

    /**
     * Accepts ?platform=PC,Nintendo Switch as well as repeated ?platform[]=PC.
     *
     * @return list<string>
     */
    private static function list(Request $request, string $key): array
    {
        $all = $request->query->all();
        $raw = $all[$key] ?? [];
        $values = \is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(array_map(static fn ($v) => trim((string) $v), $values), static fn ($v) => '' !== $v)));
    }
}
