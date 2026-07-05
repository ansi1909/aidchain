<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crea tabla coordinator_key para historial de llaves y migra datos existentes.
 */
final class Version20260703220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla coordinator_key para historial de llaves y migra datos existentes';
    }

    public function up(Schema $schema): void
    {
        // Crear tabla coordinator_key
        $this->addSql('CREATE TABLE coordinator_key (id INT AUTO_INCREMENT NOT NULL, public_key TEXT NOT NULL, activo TINYINT(1) NOT NULL, fecha_activacion DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', fecha_revocacion DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', coordinator_id INT NOT NULL, INDEX IDX_coordinator_key_coordinator (coordinator_id), PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE coordinator_key ADD CONSTRAINT FK_coordinator_key_coordinator FOREIGN KEY (coordinator_id) REFERENCES coordinator (id)');

        // Migrar datos existentes de coordinator.public_key a coordinator_key
        $this->addSql('INSERT INTO coordinator_key (public_key, activo, fecha_activacion, fecha_revocacion, coordinator_id) SELECT public_key, 1, created_at, NULL, id FROM coordinator');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coordinator_key DROP FOREIGN KEY FK_coordinator_key_coordinator');
        $this->addSql('DROP TABLE coordinator_key');
    }
}
