<?php

declare(strict_types=1);

namespace App\Home\Provider;

use App\Catalog\CatalogReadModel;
use App\Catalog\ProductListQuery;
use App\Entity\HomeBlock;
use App\Home\HomeBlockProvider;
use App\Home\HomeLayout;

/**
 * Base for blocks that are a fixed Store query (in stock only). Subclasses pick the sort and
 * may narrow it with block settings (e.g. `genre`, `platform`).
 */
abstract class CatalogQueryProvider implements HomeBlockProvider
{
    public function __construct(private readonly CatalogReadModel $catalog)
    {
    }

    public function itemKind(): string
    {
        return 'product';
    }

    abstract protected function sort(): string;

    public function items(HomeBlock $block): array
    {
        $settings = $block->getSettings();
        $genre = $settings['genre'] ?? null;
        $platform = $settings['platform'] ?? null;

        $query = new ProductListQuery(
            platforms: \is_string($platform) ? [$platform] : [],
            genre: \is_string($genre) ? $genre : null,
            inStockOnly: true,
            sort: $this->sort(),
            limit: HomeLayout::limit($block),
        );

        $items = $this->catalog->listProducts($query)['items'];
        if ('discount' === $this->sort()) {
            $items = array_values(array_filter($items, static fn (array $p): bool => null !== $p['discountPercent']));
        }

        return $items;
    }
}
