<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707231358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hace nullable item, cantidad, unidad, shelter y organization en inventory_event para soportar eventos de configuración. Agrega columna datos_configuracion (JSON).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_event MODIFY item VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_event MODIFY cantidad DECIMAL(12, 3) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_event MODIFY unidad VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_event MODIFY shelter_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_event MODIFY organization_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_event ADD datos_configuracion JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_event DROP datos_configuracion');
        $this->addSql('ALTER TABLE inventory_event MODIFY shelter_id INT NOT NULL');
        $this->addSql('ALTER TABLE inventory_event MODIFY organization_id INT NOT NULL');
        $this->addSql('ALTER TABLE inventory_event MODIFY unidad VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE inventory_event MODIFY cantidad DECIMAL(12, 3) NOT NULL');
        $this->addSql('ALTER TABLE inventory_event MODIFY item VARCHAR(120) NOT NULL');
    }
}
