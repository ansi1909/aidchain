<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega la columna `activo` a la tabla shelter para soportar la
 * inactivación (soft-delete) de refugios desde el módulo de administración,
 * preservando la integridad histórica del censo y del ledger.
 */
final class Version20260706220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega columna activo a shelter (soft-inactivate de refugios)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shelter ADD activo TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shelter DROP activo');
    }
}
