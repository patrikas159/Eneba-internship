<?php

declare(strict_types=1);

namespace App\Home\Provider;

/** Products of settings.genre (and optional settings.platform), newest first. */
final class GenreProvider extends CatalogQueryProvider
{
    public function type(): string
    {
        return 'genre';
    }

    protected function sort(): string
    {
        return 'newest';
    }
}
