<?php

declare(strict_types=1);

namespace App\Home;

use App\Entity\HomeBlock;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Supplies the items of one homepage block type (e.g. "deals"). Each block on GET /api/home
 * is fed by the provider whose type() matches the block's `type` column, so adding a new
 * block kind means adding a provider class, not touching the frontend's data.
 */
#[AutoconfigureTag(self::TAG)]
interface HomeBlockProvider
{
    public const TAG = 'app.home_block_provider';

    /** Block type this provider serves, as stored in home_blocks.type. */
    public function type(): string;

    /** Item kind the frontend should render: "product" cards or "banner" slides. */
    public function itemKind(): string;

    /**
     * @return list<array<string, mixed>>
     */
    public function items(HomeBlock $block): array;
}
