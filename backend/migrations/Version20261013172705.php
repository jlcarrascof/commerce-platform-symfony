<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013172705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable deleted_at column to category and product for soft deletes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP deleted_at');
        $this->addSql('ALTER TABLE product DROP deleted_at');
    }
}
