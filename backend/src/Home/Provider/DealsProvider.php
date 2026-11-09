<?php

declare(strict_types=1);

namespace App\Home\Provider;

/** Biggest discounts first; only discounted products. */
final class DealsProvider extends CatalogQueryProvider
{
    public function type(): string
    {
        return 'deals';
    }

    protected function sort(): string
    {
        return 'discount';
    }
}
