<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega campo documento a la tabla beneficiary para identificación y búsqueda.
 */
final class Version20260703210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega campo documento a beneficiary para identificación legal';
    }

    public function up(Schema $schema): void
    {
        // Primero agregar la columna como nullable para evitar conflictos con datos existentes
        $this->addSql('ALTER TABLE beneficiary ADD documento VARCHAR(50) DEFAULT NULL');

        // Asignar valores temporales únicos a registros existentes para poder crear el índice único
        $this->addSql('UPDATE beneficiary SET documento = CONCAT("TEMP-", id) WHERE documento IS NULL OR documento = ""');

        // Crear el índice único
        $this->addSql('CREATE UNIQUE INDEX UNIQ_beneficiary_documento ON beneficiary (documento)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_beneficiary_documento ON beneficiary');
        $this->addSql('ALTER TABLE beneficiary DROP documento');
    }
}
