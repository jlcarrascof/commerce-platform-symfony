<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenant (id SERIAL NOT NULL, name VARCHAR(150) NOT NULL, slug VARCHAR(160) NOT NULL, plan VARCHAR(20) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1664FC1E989D9B62 ON tenant (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenant');
    }
}
