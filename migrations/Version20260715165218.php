<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715165218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beneficiary ADD es_representante TINYINT NOT NULL DEFAULT 1, ADD representante_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446A2FD20D28 FOREIGN KEY (representante_id) REFERENCES beneficiary (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_7ABF446A2FD20D28 ON beneficiary (representante_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beneficiary DROP FOREIGN KEY FK_7ABF446A2FD20D28');
        $this->addSql('DROP INDEX IDX_7ABF446A2FD20D28 ON beneficiary');
        $this->addSql('ALTER TABLE beneficiary DROP es_representante, DROP representante_id');
    }
}
