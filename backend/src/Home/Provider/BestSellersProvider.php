<?php

declare(strict_types=1);

namespace App\Home\Provider;

use App\Catalog\CatalogReadModel;
use App\Entity\HomeBlock;
use App\Home\HomeBlockProvider;
use App\Home\HomeLayout;

/** In-stock products ranked by keys sold. */
final class BestSellersProvider implements HomeBlockProvider
{
    public function __construct(private readonly CatalogReadModel $catalog)
    {
    }

    public function type(): string
    {
        return 'best_sellers';
    }

    public function itemKind(): string
    {
        return 'product';
    }

    public function items(HomeBlock $block): array
    {
        return $this->catalog->bestSellers(HomeLayout::limit($block));
    }
}
