<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Banner;
use App\Entity\HomeBlock;
use App\Entity\Offer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\ProductKey;
use App\Entity\Seller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Loads demo catalog data from data/seed/products.json (offline; the IGDB importer writes the same format).
 */
#[AsCommand(name: 'app:seed', description: 'Seed demo catalog, sellers, offers, keys, banners and home blocks')]
final class SeedCommand extends Command
{
    private const SELLERS = [
        ['KeyHaven', 'keyhaven', 4.8],
        ['PixelVault', 'pixelvault', 4.6],
        ['GameDrop', 'gamedrop', 4.3],
        ['NordKeys', 'nordkeys', 4.9],
        ['LootLane', 'lootlane', 4.1],
    ];

    private const KEY_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /** @var array<string, true> */
    private array $usedCodes = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('purge', null, InputOption::VALUE_NONE, 'Delete orders and all catalog/homepage data before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = $this->projectDir.'/data/seed/products.json';
        $rows = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        if (!\is_array($rows) || [] === $rows) {
            $io->error("No products in {$file}");

            return Command::FAILURE;
        }

        if ($input->getOption('purge')) {
            $this->purge();
            $io->note('Purged existing orders, catalog and homepage data.');
        } elseif ($this->em->getRepository(Product::class)->count([]) > 0) {
            $io->success('Products already exist; nothing to do (use --purge to reseed).');

            return Command::SUCCESS;
        }

        $counts = $this->em->wrapInTransaction(fn () => $this->seed($rows));

        $io->success(\sprintf(
            'Seeded %d products, %d sellers, %d offers, %d keys, %d banners, %d home blocks.',
            $counts['products'], $counts['sellers'], $counts['offers'], $counts['keys'], $counts['banners'], $counts['blocks'],
        ));

        return Command::SUCCESS;
    }

    private function purge(): void
    {
        // Orders reference offers (order_items.offer_id has no cascade), so they must go first.
        $classes = [
            Payment::class, OrderItem::class, Order::class,
            ProductKey::class, Offer::class, Product::class, Seller::class,
            Banner::class, HomeBlock::class,
        ];
        $this->em->wrapInTransaction(function () use ($classes): void {
            foreach ($classes as $class) {
                $this->em->createQuery("DELETE FROM {$class} e")->execute();
            }
        });
        $this->em->clear();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, int>
     */
    private function seed(array $rows): array
    {
        mt_srand(42);
        $counts = ['products' => 0, 'sellers' => 0, 'offers' => 0, 'keys' => 0, 'banners' => 0, 'blocks' => 0];

        $sellers = [];
        foreach (self::SELLERS as [$name, $slug, $rating]) {
            $seller = (new Seller($name, $slug))->setRating($rating);
            $this->em->persist($seller);
            $sellers[] = $seller;
            ++$counts['sellers'];
        }

        $slugs = [];
        foreach ($rows as $row) {
            $product = new Product($row['slug'], $row['name'], $row['description'], $row['platform'], $row['region']);
            $product->setGenres($row['genres'] ?? []);
            $product->setCoverUrl($row['coverUrl'] ?? null);
            $product->setIgdbId(isset($row['igdbId']) ? (int) $row['igdbId'] : null);
            if (!empty($row['releaseDate'])) {
                $product->setReleaseDate(new \DateTimeImmutable($row['releaseDate']));
            }
            $this->em->persist($product);
            $slugs[] = $row['slug'];
            ++$counts['products'];

            // 2-4 distinct sellers per product.
            $pool = $sellers;
            $this->shuffle($pool);
            foreach (\array_slice($pool, 0, mt_rand(2, 4)) as $seller) {
                $offer = new Offer($product, $seller, mt_rand(299, 6999));
                if (mt_rand(0, 1) === 1) {
                    $offer->setOriginalPriceCents((int) round($offer->getPriceCents() * (1 + mt_rand(10, 60) / 100)));
                }
                $this->em->persist($offer);
                ++$counts['offers'];

                $keyCount = mt_rand(3, 8);
                for ($i = 0; $i < $keyCount; ++$i) {
                    $this->em->persist(new ProductKey($offer, $this->newCode()));
                    ++$counts['keys'];
                }
            }

            $this->em->flush();
        }

        $banners = [
            ['Spring Sale', 'Up to 60% off top PC titles', $slugs[0]],
            ['New on PlayStation 5', 'Fresh keys from trusted sellers', $slugs[min(14, \count($slugs) - 1)]],
            ['Nintendo Switch favourites', 'Instant digital delivery', $slugs[\count($slugs) - 1]],
        ];
        foreach ($banners as $i => [$title, $subtitle, $slug]) {
            $banner = new Banner($title, \sprintf('https://picsum.photos/seed/banner%d/1200/400', $i + 1), '/product/'.$slug, $subtitle);
            $banner->setPosition($i);
            $this->em->persist($banner);
            ++$counts['banners'];
        }

        $blocks = [
            ['banners', 'Featured', []],
            ['best_sellers', 'Best sellers', ['limit' => 12]],
            ['deals', 'Hot deals', ['limit' => 12]],
            ['new_releases', 'New releases', ['limit' => 12]],
            ['genre', 'RPG picks', ['genre' => 'RPG', 'limit' => 12]],
        ];
        foreach ($blocks as $i => [$type, $title, $settings]) {
            $block = (new HomeBlock($type, $title))->setPosition($i)->setSettings($settings);
            $this->em->persist($block);
            ++$counts['blocks'];
        }

        $this->em->flush();

        return $counts;
    }

    /** Fisher-Yates using mt_rand so the result follows mt_srand(42). */
    private function shuffle(array &$items): void
    {
        for ($i = \count($items) - 1; $i > 0; --$i) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }

    private function newCode(): string
    {
        do {
            $chars = '';
            foreach (str_split(random_bytes(15)) as $byte) {
                $chars .= self::KEY_ALPHABET[\ord($byte) % 36];
            }
            $code = implode('-', str_split($chars, 5));
        } while (isset($this->usedCodes[$code]));
        $this->usedCodes[$code] = true;

        return $code;
    }
}
