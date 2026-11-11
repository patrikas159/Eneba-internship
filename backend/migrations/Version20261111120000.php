<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data fix: seeded banners linked to a single product page. Point them at the Store listing they
 * advertise instead (already-seeded databases only; the seed command was updated to match).
 * Only rows still carrying a seeded /product/ link are touched, so manual edits survive.
 */
final class Version20261111120000 extends AbstractMigration
{
    private const LINKS = [
        'Spring Sale' => '/store?platform=PC&onSale=true&sort=discount',
        'New on PlayStation 5' => '/store?platform=PlayStation%205&sort=newest',
        'Nintendo Switch favourites' => '/store?platform=Nintendo%20Switch',
    ];

    public function getDescription(): string
    {
        return 'Link seeded banners to filtered Store listings instead of single products';
    }

    public function up(Schema $schema): void
    {
        foreach (self::LINKS as $title => $link) {
            $this->addSql(
                "UPDATE banners SET link_url = :link WHERE title = :title AND link_url LIKE '/product/%'",
                ['link' => $link, 'title' => $title],
            );
        }
    }

    public function down(Schema $schema): void
    {
        // The original per-product links are not recoverable; the Store links stay valid.
    }
}
