<?php

declare(strict_types=1);

namespace App\Home\Provider;

/** Most recent release dates first. */
final class NewReleasesProvider extends CatalogQueryProvider
{
    public function type(): string
    {
        return 'new_releases';
    }

    protected function sort(): string
    {
        return 'newest';
    }
}
