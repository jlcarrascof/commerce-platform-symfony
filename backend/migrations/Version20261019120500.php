<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261019120500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant_id to product, category, order and customer.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADEA9FDD75 FOREIGN KEY (tenant_id) REFERENCES tenant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D34A04ADEA9FDD75 ON product (tenant_id)');

        $this->addSql('ALTER TABLE category ADD tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1EA9FDD75 FOREIGN KEY (tenant_id) REFERENCES tenant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_64C19C1EA9FDD75 ON category (tenant_id)');

        $this->addSql('ALTER TABLE "order" ADD tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F5299398EA9FDD75 FOREIGN KEY (tenant_id) REFERENCES tenant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_F5299398EA9FDD75 ON "order" (tenant_id)');

        $this->addSql('ALTER TABLE customer ADD tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E09EA9FDD75 FOREIGN KEY (tenant_id) REFERENCES tenant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_81398E09EA9FDD75 ON customer (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP CONSTRAINT FK_D34A04ADEA9FDD75');
        $this->addSql('DROP INDEX IDX_D34A04ADEA9FDD75');
        $this->addSql('ALTER TABLE product DROP tenant_id');

        $this->addSql('ALTER TABLE category DROP CONSTRAINT FK_64C19C1EA9FDD75');
        $this->addSql('DROP INDEX IDX_64C19C1EA9FDD75');
        $this->addSql('ALTER TABLE category DROP tenant_id');

        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F5299398EA9FDD75');
        $this->addSql('DROP INDEX IDX_F5299398EA9FDD75');
        $this->addSql('ALTER TABLE "order" DROP tenant_id');

        $this->addSql('ALTER TABLE customer DROP CONSTRAINT FK_81398E09EA9FDD75');
        $this->addSql('DROP INDEX IDX_81398E09EA9FDD75');
        $this->addSql('ALTER TABLE customer DROP tenant_id');
    }
}
