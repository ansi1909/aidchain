<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708232353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beneficiary RENAME INDEX uniq_beneficiary_documento TO UNIQ_7ABF446AB6B12EC7');
        $this->addSql('ALTER TABLE coordinator RENAME INDEX uniq_coordinator_documento TO UNIQ_15FE0E6AB6B12EC7');
        $this->addSql('ALTER TABLE coordinator_key CHANGE public_key public_key LONGTEXT NOT NULL, CHANGE fecha_activacion fecha_activacion DATETIME NOT NULL, CHANGE fecha_revocacion fecha_revocacion DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE coordinator_key RENAME INDEX idx_coordinator_key_coordinator TO IDX_7E1CA0E6E7877946');
        $this->addSql('ALTER TABLE inventory_event CHANGE tipo tipo VARCHAR(30) NOT NULL');
        $this->addSql('ALTER TABLE shelter_stock CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beneficiary RENAME INDEX uniq_7abf446ab6b12ec7 TO UNIQ_beneficiary_documento');
        $this->addSql('ALTER TABLE coordinator RENAME INDEX uniq_15fe0e6ab6b12ec7 TO UNIQ_coordinator_documento');
        $this->addSql('ALTER TABLE coordinator_key CHANGE public_key public_key TEXT NOT NULL, CHANGE fecha_activacion fecha_activacion DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE fecha_revocacion fecha_revocacion DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE coordinator_key RENAME INDEX idx_7e1ca0e6e7877946 TO IDX_coordinator_key_coordinator');
        $this->addSql('ALTER TABLE inventory_event CHANGE tipo tipo ENUM(\'out_dispatch\', \'out_beneficiary\', \'in_reception\', \'in_stock\') NOT NULL COMMENT \'(DC2Type:AppEnumEventType)\'');
        $this->addSql('ALTER TABLE shelter_stock CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
