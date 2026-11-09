<?php

declare(strict_types=1);

namespace App\Home\Provider;

use App\Entity\Banner;
use App\Entity\HomeBlock;
use App\Home\HomeBlockProvider;
use App\Home\HomeLayout;
use App\Repository\BannerRepository;

/** Hero carousel: active rows of the `banners` table. */
final class BannersProvider implements HomeBlockProvider
{
    public function __construct(private readonly BannerRepository $banners)
    {
    }

    public function type(): string
    {
        return 'banners';
    }

    public function itemKind(): string
    {
        return 'banner';
    }

    public function items(HomeBlock $block): array
    {
        $rows = $this->banners->findBy(['active' => true], ['position' => 'ASC', 'id' => 'ASC'], HomeLayout::limit($block, 5, 10));

        return array_map(static fn (Banner $b): array => [
            'id' => (int) $b->getId(),
            'title' => $b->getTitle(),
            'subtitle' => $b->getSubtitle(),
            'imageUrl' => $b->getImageUrl(),
            'linkUrl' => $b->getLinkUrl(),
        ], $rows);
    }
}
