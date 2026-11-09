<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Trigram GIN index on products.name for typo-tolerant and partial-name search
 * (similarity(), word_similarity(), ILIKE '%...%'). pg_trgm lowercases trigrams itself,
 * so a plain column index serves case-insensitive queries.
 */
final class Version20261109160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trigram GIN index on products.name';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_products_name_trgm ON products USING gin (name gin_trgm_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_products_name_trgm');
    }
}
