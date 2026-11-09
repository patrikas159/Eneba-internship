<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enables pg_trgm (trigram similarity) used by typo-tolerant product search.
 * Runs before any table migration so Neon gets the extension too.
 */
final class Version20261109000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable pg_trgm extension for fuzzy search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP EXTENSION IF EXISTS pg_trgm');
    }
}
