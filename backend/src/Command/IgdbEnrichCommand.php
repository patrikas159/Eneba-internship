<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * One-off importer: looks every game in data/seed/products.json up on IGDB and writes
 * igdbId, coverUrl and artworkUrl back into the file. app:seed then works fully offline.
 *
 * Credentials (Twitch app) come from IGDB_CLIENT_ID / IGDB_CLIENT_SECRET in .env.local.
 * To fix a wrong match, put the right igdbId in the JSON row and rerun with --force.
 */
#[AsCommand(name: 'app:igdb:enrich', description: 'Fill igdbId/coverUrl/artworkUrl in the seed JSON from the IGDB API')]
final class IgdbEnrichCommand extends Command
{
    private const TOKEN_URL = 'https://id.twitch.tv/oauth2/token';
    private const GAMES_URL = 'https://api.igdb.com/v4/games';
    private const IMAGE_URL = 'https://images.igdb.com/igdb/image/upload/%s/%s.jpg';
    private const FIELDS = 'fields id,name,first_release_date,cover.image_id,artworks.image_id,screenshots.image_id;';

    /** Store names that IGDB lists under a different title. */
    private const SEARCH_OVERRIDES = [
        'Counter-Strike 2 Prime Status' => 'Counter-Strike 2',
        'Minecraft: Java & Bedrock Edition' => 'Minecraft',
    ];

    private ?string $token = null;

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire(env: 'default::IGDB_CLIENT_ID')]
        private readonly ?string $clientId = null,
        #[Autowire(env: 'default::IGDB_CLIENT_SECRET')]
        private readonly ?string $clientSecret = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Also refresh rows that already have a coverUrl')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print matches without writing the file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->clientId || !$this->clientSecret) {
            $io->error('IGDB_CLIENT_ID and IGDB_CLIENT_SECRET must be set (backend/.env.local).');

            return Command::FAILURE;
        }

        $path = $this->projectDir.'/data/seed/products.json';
        /** @var list<array<string, mixed>> $rows */
        $rows = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        $force = (bool) $input->getOption('force');

        /** @var array<string, array<string, mixed>|null> $byName one lookup per game, platforms share it */
        $byName = [];
        $updated = 0;
        $missing = [];
        foreach ($rows as $i => $row) {
            if (!$force && !empty($row['coverUrl'])) {
                continue;
            }
            $name = (string) $row['name'];
            $key = isset($row['igdbId']) ? 'id:'.$row['igdbId'] : 'name:'.$name;
            if (!\array_key_exists($key, $byName)) {
                $byName[$key] = isset($row['igdbId']) ? $this->byId((int) $row['igdbId']) : $this->search($name);
                usleep(300_000); // IGDB allows 4 requests per second
            }
            $game = $byName[$key];
            if (null === $game || !isset($game['cover']['image_id'])) {
                $missing[] = $name;
                continue;
            }

            $art = $game['artworks'][0]['image_id'] ?? $game['screenshots'][0]['image_id'] ?? null;
            $rows[$i]['igdbId'] = (int) $game['id'];
            $rows[$i]['coverUrl'] = \sprintf(self::IMAGE_URL, 't_cover_big', $game['cover']['image_id']);
            $rows[$i]['artworkUrl'] = null !== $art ? \sprintf(self::IMAGE_URL, 't_1080p', $art) : null;
            ++$updated;
            $io->writeln(\sprintf('  %-45s -> #%d %s', $name, $game['id'], $game['name']));
        }

        if ([] !== $missing) {
            $io->warning('No IGDB match with a cover for: '.implode(', ', array_unique($missing)));
        }
        if ($input->getOption('dry-run')) {
            $io->note(\sprintf('Dry run: %d rows would be updated.', $updated));

            return Command::SUCCESS;
        }

        // One product per line keeps diffs of the seed file readable.
        $lines = array_map(
            static fn (array $r): string => '  '.json_encode($r, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            $rows,
        );
        file_put_contents($path, "[\n".implode(",\n", $lines)."\n]\n");
        $io->success(\sprintf('Updated %d of %d rows in %s.', $updated, \count($rows), 'data/seed/products.json'));

        return Command::SUCCESS;
    }

    /** Best match for a store name: exact (case/punctuation-insensitive) title first, else IGDB's top hit with a cover. */
    private function search(string $name): ?array
    {
        $term = self::SEARCH_OVERRIDES[$name] ?? $name;
        $games = $this->query(\sprintf('search "%s"; %s where version_parent = null; limit 10;', addcslashes($term, '"\\'), self::FIELDS));

        $wanted = self::normalize($term);
        foreach ($games as $game) {
            if (self::normalize((string) $game['name']) === $wanted && isset($game['cover'])) {
                return $game;
            }
        }
        foreach ($games as $game) {
            if (isset($game['cover'])) {
                return $game;
            }
        }

        return null;
    }

    private function byId(int $id): ?array
    {
        return $this->query(\sprintf('%s where id = %d;', self::FIELDS, $id))[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    private function query(string $body): array
    {
        return $this->http->request('POST', self::GAMES_URL, [
            'headers' => [
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer '.$this->token(),
                'Accept' => 'application/json',
            ],
            'body' => $body,
        ])->toArray();
    }

    private function token(): string
    {
        return $this->token ??= (string) $this->http->request('POST', self::TOKEN_URL, [
            // Form body, not query string: HTTP exceptions quote the URL and must not leak the secret.
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ],
        ])->toArray()['access_token'];
    }

    private static function normalize(string $s): string
    {
        $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s);

        return (string) preg_replace('/[^a-z0-9]+/', '', $s);
    }
}
