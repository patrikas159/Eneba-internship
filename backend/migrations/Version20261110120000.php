<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data fix: the first IGDB artwork of Elden Ring (ar3m1o) is an almost black ultra-wide strip,
 * so the "Spring Sale" banner looked empty. Already-seeded databases (app:seed is a no-op there)
 * get a bright 16:9 screenshot instead; the seed JSON was updated to match.
 */
final class Version20261110120000 extends AbstractMigration
{
    private const OLD = 'https://images.igdb.com/igdb/image/upload/t_1080p/ar3m1o.jpg';
    private const NEW = 'https://images.igdb.com/igdb/image/upload/t_1080p/scu5kw.jpg';

    public function getDescription(): string
    {
        return 'Replace the near-black Elden Ring banner image';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE banners SET image_url = :new WHERE image_url = :old', ['new' => self::NEW, 'old' => self::OLD]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE banners SET image_url = :old WHERE image_url = :new', ['new' => self::NEW, 'old' => self::OLD]);
    }
}
