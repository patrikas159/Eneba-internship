<?php

declare(strict_types=1);

namespace App\Home;

use App\Entity\HomeBlock;
use App\Repository\HomeBlockRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Builds the homepage: active blocks in `position` order, each filled by its provider.
 * Blocks with an unknown type or no items are left out.
 */
final class HomeLayout
{
    /** @var array<string, HomeBlockProvider> */
    private array $providers = [];

    /**
     * @param iterable<HomeBlockProvider> $providers
     */
    public function __construct(
        private readonly HomeBlockRepository $blocks,
        private readonly LoggerInterface $logger,
        #[AutowireIterator(HomeBlockProvider::TAG)] iterable $providers,
    ) {
        foreach ($providers as $provider) {
            $this->providers[$provider->type()] = $provider;
        }
    }

    /**
     * @return list<array{id: int, type: string, title: string, itemKind: string, settings: object, items: list<array<string, mixed>>}>
     */
    public function build(): array
    {
        $result = [];
        foreach ($this->blocks->findBy(['active' => true], ['position' => 'ASC', 'id' => 'ASC']) as $block) {
            /** @var HomeBlock $block */
            $provider = $this->providers[$block->getType()] ?? null;
            if (null === $provider) {
                $this->logger->warning('No provider for home block type "{type}" (block {id}).', ['type' => $block->getType(), 'id' => $block->getId()]);
                continue;
            }
            $items = $provider->items($block);
            if ([] === $items) {
                continue;
            }
            $result[] = [
                'id' => (int) $block->getId(),
                'type' => $block->getType(),
                'title' => $block->getTitle(),
                'itemKind' => $provider->itemKind(),
                'settings' => (object) $block->getSettings(),
                'items' => $items,
            ];
        }

        return $result;
    }

    /** Reads a positive int setting, clamped to [1, $max]. */
    public static function limit(HomeBlock $block, int $default = 12, int $max = 24): int
    {
        $limit = $block->getSettings()['limit'] ?? $default;

        return \is_int($limit) ? max(1, min($max, $limit)) : $default;
    }
}
