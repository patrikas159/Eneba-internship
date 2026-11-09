<?php

declare(strict_types=1);

namespace App\Catalog;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Read-side SQL for the Store, Search and Product page.
 *
 * Price and stock always come from live data: a product's price is its cheapest active offer that
 * still has at least one available key, and stock is the number of available keys.
 * Name search uses pg_trgm (GIN index idx_products_name_trgm): substring match OR word similarity
 * above SEARCH_THRESHOLD, so typos and partial names still match.
 */
final class CatalogReadModel
{
    public const SEARCH_THRESHOLD = 0.3;

    /** Per-product aggregate over active offers: cheapest in-stock offer, seller count, total stock. */
    // Stock = sellable keys: available, or reserved by an order whose reservation expired
    // (same rule as OrderService's key-locking query). Keep the three copies below in sync.
    private const PRICE_CTE = <<<'SQL'
        offer_stock AS (
            SELECT o.id, o.product_id, o.price_cents, o.original_price_cents, o.currency,
                   (SELECT count(*) FROM product_keys k WHERE k.offer_id = o.id AND (k.status = 'available' OR (k.status = 'reserved' AND k.reserved_until < LOCALTIMESTAMP))) AS stock
            FROM offers o
            WHERE o.active
        ),
        best AS (
            SELECT DISTINCT ON (product_id) product_id, price_cents, original_price_cents, currency
            FROM offer_stock
            WHERE stock > 0
            ORDER BY product_id, price_cents, id
        ),
        agg AS (
            SELECT product_id, count(*) FILTER (WHERE stock > 0) AS offer_count, coalesce(sum(stock), 0) AS stock
            FROM offer_stock
            GROUP BY product_id
        )
        SQL;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int}
     */
    public function listProducts(ProductListQuery $query): array
    {
        $where = ['TRUE'];
        $params = [];
        $types = [];

        if (null !== $query->q) {
            $this->ensureSearchThreshold();
            $where[] = "(p.name ILIKE :like OR :q <% p.name)";
            $params['q'] = $query->q;
            $params['like'] = '%'.addcslashes($query->q, '%_\\').'%';
        }
        if ([] !== $query->platforms) {
            $where[] = 'p.platform IN (:platforms)';
            $params['platforms'] = $query->platforms;
            $types['platforms'] = ArrayParameterType::STRING;
        }
        if ([] !== $query->regions) {
            $where[] = 'p.region IN (:regions)';
            $params['regions'] = $query->regions;
            $types['regions'] = ArrayParameterType::STRING;
        }
        if (null !== $query->genre) {
            $where[] = 'p.genres::jsonb @> jsonb_build_array(CAST(:genre AS text))';
            $params['genre'] = $query->genre;
        }
        if (null !== $query->minPriceCents) {
            $where[] = 'b.price_cents >= :minPrice';
            $params['minPrice'] = $query->minPriceCents;
        }
        if (null !== $query->maxPriceCents) {
            $where[] = 'b.price_cents <= :maxPrice';
            $params['maxPrice'] = $query->maxPriceCents;
        }
        if ($query->inStockOnly) {
            $where[] = 'b.product_id IS NOT NULL';
        }

        $orderBy = match ($query->sort) {
            'relevance' => 'word_similarity(:q, p.name) DESC, similarity(:q, p.name) DESC, p.name ASC',
            'price_asc' => 'b.price_cents ASC NULLS LAST, p.name ASC',
            'price_desc' => 'b.price_cents DESC NULLS LAST, p.name ASC',
            'newest' => 'p.release_date DESC NULLS LAST, p.id DESC',
            'name' => 'p.name ASC',
            'discount' => self::DISCOUNT_SQL.' DESC NULLS LAST, b.price_cents ASC',
        };

        $params['limit'] = $query->limit;
        $params['offset'] = ($query->page - 1) * $query->limit;

        $sql = 'WITH '.self::PRICE_CTE.'
            SELECT '.self::cardColumns().', count(*) OVER () AS total
            FROM products p
            LEFT JOIN best b ON b.product_id = p.id
            LEFT JOIN agg a ON a.product_id = p.id
            WHERE '.implode(' AND ', $where).'
            ORDER BY '.$orderBy.'
            LIMIT :limit OFFSET :offset';

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);
        $total = (int) ($rows[0]['total'] ?? $this->countOnly($where, $params, $types));

        return [
            'items' => array_map(self::card(...), $rows),
            'total' => $total,
            'page' => $query->page,
            'limit' => $query->limit,
        ];
    }

    /**
     * In-stock products ranked by keys sold (paid orders), then by seller count.
     *
     * @return list<array<string, mixed>>
     */
    public function bestSellers(int $limit): array
    {
        $sql = 'WITH '.self::PRICE_CTE.',
            sold AS (
                SELECT o.product_id, count(*) AS sold
                FROM product_keys k
                JOIN offers o ON o.id = k.offer_id
                WHERE k.status = \'sold\'
                GROUP BY o.product_id
            )
            SELECT '.self::cardColumns().'
            FROM products p
            JOIN best b ON b.product_id = p.id
            LEFT JOIN agg a ON a.product_id = p.id
            LEFT JOIN sold s ON s.product_id = p.id
            ORDER BY coalesce(s.sold, 0) DESC, a.offer_count DESC, p.name ASC
            LIMIT :limit';

        return array_map(self::card(...), $this->connection->fetchAllAssociative($sql, ['limit' => $limit]));
    }

    /**
     * Typo-tolerant autocomplete for the global search box.
     *
     * @return list<array<string, mixed>>
     */
    public function suggest(string $q, int $limit = 8): array
    {
        $this->ensureSearchThreshold();

        $rows = $this->connection->fetchAllAssociative(
            'WITH '.self::PRICE_CTE.'
            SELECT '.self::cardColumns().'
            FROM products p
            LEFT JOIN best b ON b.product_id = p.id
            LEFT JOIN agg a ON a.product_id = p.id
            WHERE p.name ILIKE :like OR :q <% p.name
            ORDER BY (p.name ILIKE :prefix) DESC, word_similarity(:q, p.name) DESC, similarity(:q, p.name) DESC, p.name
            LIMIT :limit',
            [
                'q' => $q,
                'like' => '%'.addcslashes($q, '%_\\').'%',
                'prefix' => addcslashes($q, '%_\\').'%',
                'limit' => $limit,
            ],
        );

        return array_map(self::card(...), $rows);
    }

    /**
     * Distinct filter values with product counts, plus the overall price range (for the Store sidebar).
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $facet = fn (string $column): array => array_map(
            static fn (array $r) => ['value' => $r['value'], 'count' => (int) $r['count']],
            $this->connection->fetchAllAssociative("SELECT {$column} AS value, count(*) AS count FROM products GROUP BY {$column} ORDER BY count(*) DESC, {$column}"),
        );

        $genres = array_map(
            static fn (array $r) => ['value' => $r['value'], 'count' => (int) $r['count']],
            $this->connection->fetchAllAssociative('SELECT g AS value, count(*) AS count FROM products p, jsonb_array_elements_text(p.genres::jsonb) g GROUP BY g ORDER BY count(*) DESC, g'),
        );

        $range = $this->connection->fetchAssociative('WITH '.self::PRICE_CTE.' SELECT min(price_cents) AS min, max(price_cents) AS max FROM best') ?: [];

        return [
            'platforms' => $facet('platform'),
            'regions' => $facet('region'),
            'genres' => $genres,
            'price' => ['minCents' => (int) ($range['min'] ?? 0), 'maxCents' => (int) ($range['max'] ?? 0)],
            'sorts' => ProductListQuery::SORTS,
        ];
    }

    /**
     * Product page: details plus every active offer (cheapest first) with seller info and stock.
     *
     * @return array<string, mixed>|null
     */
    public function productBySlug(string $slug): ?array
    {
        $row = $this->connection->fetchAssociative(
            'WITH '.self::PRICE_CTE.'
            SELECT '.self::cardColumns().', p.description
            FROM products p
            LEFT JOIN best b ON b.product_id = p.id
            LEFT JOIN agg a ON a.product_id = p.id
            WHERE p.slug = :slug',
            ['slug' => $slug],
        );
        if (false === $row) {
            return null;
        }

        $offers = $this->connection->fetchAllAssociative(
            "SELECT o.id, o.price_cents, o.original_price_cents, o.currency,
                    s.id AS seller_id, s.name AS seller_name, s.slug AS seller_slug, s.rating AS seller_rating,
                    (SELECT count(*) FROM product_keys k WHERE k.offer_id = o.id AND (k.status = 'available' OR (k.status = 'reserved' AND k.reserved_until < LOCALTIMESTAMP))) AS stock
            FROM offers o
            JOIN sellers s ON s.id = o.seller_id
            WHERE o.product_id = :id AND o.active
            ORDER BY (SELECT count(*) FROM product_keys k WHERE k.offer_id = o.id AND (k.status = 'available' OR (k.status = 'reserved' AND k.reserved_until < LOCALTIMESTAMP))) = 0, o.price_cents, o.id",
            ['id' => $row['id']],
        );

        $product = self::card($row);
        $product['description'] = $row['description'];
        $product['offers'] = array_map(static fn (array $o) => [
            'id' => (int) $o['id'],
            'priceCents' => (int) $o['price_cents'],
            'originalPriceCents' => null === $o['original_price_cents'] ? null : (int) $o['original_price_cents'],
            'discountPercent' => self::discount($o['price_cents'], $o['original_price_cents']),
            'currency' => $o['currency'],
            'stock' => (int) $o['stock'],
            'inStock' => (int) $o['stock'] > 0,
            'seller' => [
                'id' => (int) $o['seller_id'],
                'name' => $o['seller_name'],
                'slug' => $o['seller_slug'],
                'rating' => round((float) $o['seller_rating'], 1),
            ],
        ], $offers);

        return $product;
    }

    private const DISCOUNT_SQL = 'CASE WHEN b.original_price_cents > b.price_cents THEN (b.original_price_cents - b.price_cents)::float / b.original_price_cents END';

    private static function cardColumns(): string
    {
        return 'p.id, p.slug, p.name, p.cover_url, p.platform, p.region, p.genres, p.release_date,
                b.price_cents, b.original_price_cents, b.currency,
                coalesce(a.offer_count, 0) AS offer_count, coalesce(a.stock, 0) AS stock';
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return array<string, mixed>
     */
    private static function card(array $r): array
    {
        $price = null === $r['price_cents'] ? null : (int) $r['price_cents'];

        return [
            'id' => (int) $r['id'],
            'slug' => $r['slug'],
            'name' => $r['name'],
            'coverUrl' => $r['cover_url'],
            'platform' => $r['platform'],
            'region' => $r['region'],
            'genres' => json_decode((string) $r['genres'], true) ?: [],
            'releaseDate' => $r['release_date'],
            'priceCents' => $price,
            'originalPriceCents' => null === $r['original_price_cents'] ? null : (int) $r['original_price_cents'],
            'discountPercent' => self::discount($r['price_cents'], $r['original_price_cents']),
            'currency' => $r['currency'] ?? 'EUR',
            'offerCount' => (int) $r['offer_count'],
            'inStock' => null !== $price,
        ];
    }

    private static function discount(mixed $price, mixed $original): ?int
    {
        if (null === $price || null === $original || (int) $original <= (int) $price) {
            return null;
        }

        return (int) round(((int) $original - (int) $price) * 100 / (int) $original);
    }

    /**
     * Page past the end: the window count is unavailable, so count separately.
     *
     * @param list<string>         $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $types
     */
    private function countOnly(array $where, array $params, array $types): int
    {
        if (0 === $params['offset']) {
            return 0;
        }
        unset($params['limit'], $params['offset']);

        return (int) $this->connection->fetchOne(
            'WITH '.self::PRICE_CTE.'
            SELECT count(*) FROM products p
            LEFT JOIN best b ON b.product_id = p.id
            LEFT JOIN agg a ON a.product_id = p.id
            WHERE '.implode(' AND ', $where),
            $params,
            $types,
        );
    }

    /**
     * Lower the word-similarity cut-off for the indexable `<%` operator. Session-level and idempotent;
     * re-sent per search so a reconnected (persistent, FrankenPHP worker) connection still gets it.
     */
    private function ensureSearchThreshold(): void
    {
        $this->connection->executeStatement(\sprintf('SET pg_trgm.word_similarity_threshold = %F', self::SEARCH_THRESHOLD));
    }
}
