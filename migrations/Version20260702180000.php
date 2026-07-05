<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega campo documento a la tabla coordinator para identificación legal.
 */
final class Version20260702180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega campo documento a coordinator para identificación legal';
    }

    public function up(Schema $schema): void
    {
        // Primero agregar la columna como nullable para evitar conflictos con datos existentes
        $this->addSql('ALTER TABLE coordinator ADD documento VARCHAR(50) DEFAULT NULL');

        // Asignar valores temporales únicos a registros existentes para poder crear el índice único
        $this->addSql('UPDATE coordinator SET documento = CONCAT("TEMP-", id) WHERE documento IS NULL OR documento = ""');

        // Ahora hacer la columna NOT NULL
        $this->addSql('ALTER TABLE coordinator CHANGE documento documento VARCHAR(50) NOT NULL');

        // Crear el índice único
        $this->addSql('CREATE UNIQUE INDEX UNIQ_coordinator_documento ON coordinator (documento)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_coordinator_documento ON coordinator');
        $this->addSql('ALTER TABLE coordinator DROP documento');
    }
}
