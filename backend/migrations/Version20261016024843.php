<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261016024843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shipping_address to order.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" ADD shipping_address VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP shipping_address');
    }
}
