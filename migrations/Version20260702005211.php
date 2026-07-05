<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260702005211 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE coordinator (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(180) NOT NULL, rol VARCHAR(30) NOT NULL, public_key LONGTEXT NOT NULL, created_at DATETIME NOT NULL, organization_id INT NOT NULL, shelter_id INT DEFAULT NULL, INDEX IDX_15FE0E6A32C8A3DE (organization_id), INDEX IDX_15FE0E6A54053EC0 (shelter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE coordinator ADD CONSTRAINT FK_15FE0E6A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE coordinator ADD CONSTRAINT FK_15FE0E6A54053EC0 FOREIGN KEY (shelter_id) REFERENCES shelter (id)');
        $this->addSql('ALTER TABLE audit_alert RENAME INDEX idx_alert_inventory_event TO IDX_10B62236C5BD521A');
        $this->addSql('ALTER TABLE beneficiary RENAME INDEX idx_beneficiary_shelter TO IDX_7ABF446A54053EC0');
        $this->addSql('ALTER TABLE inventory_event ADD coordinator_origen_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_event ADD CONSTRAINT FK_E732A1A7FCEC5EA FOREIGN KEY (coordinator_origen_id) REFERENCES coordinator (id)');
        $this->addSql('CREATE INDEX IDX_E732A1A7FCEC5EA ON inventory_event (coordinator_origen_id)');
        $this->addSql('ALTER TABLE inventory_event RENAME INDEX idx_inventory_shelter TO IDX_E732A1A754053EC0');
        $this->addSql('ALTER TABLE inventory_event RENAME INDEX idx_inventory_organization TO IDX_E732A1A732C8A3DE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE coordinator DROP FOREIGN KEY FK_15FE0E6A32C8A3DE');
        $this->addSql('ALTER TABLE coordinator DROP FOREIGN KEY FK_15FE0E6A54053EC0');
        $this->addSql('DROP TABLE coordinator');
        $this->addSql('ALTER TABLE audit_alert RENAME INDEX idx_10b62236c5bd521a TO IDX_alert_inventory_event');
        $this->addSql('ALTER TABLE beneficiary RENAME INDEX idx_7abf446a54053ec0 TO IDX_beneficiary_shelter');
        $this->addSql('ALTER TABLE inventory_event DROP FOREIGN KEY FK_E732A1A7FCEC5EA');
        $this->addSql('DROP INDEX IDX_E732A1A7FCEC5EA ON inventory_event');
        $this->addSql('ALTER TABLE inventory_event DROP coordinator_origen_id');
        $this->addSql('ALTER TABLE inventory_event RENAME INDEX idx_e732a1a754053ec0 TO IDX_inventory_shelter');
        $this->addSql('ALTER TABLE inventory_event RENAME INDEX idx_e732a1a732c8a3de TO IDX_inventory_organization');
    }
}
