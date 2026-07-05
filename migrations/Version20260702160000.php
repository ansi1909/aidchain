<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crea la tabla shelter_need para el sistema de gestión de demanda.
 *
 * Permite que los refugios reporten sus necesidades específicas y que los despachos
 * se prioricen según urgencia real, integrándose automáticamente con el ledger.
 */
final class Version20260702160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla shelter_need para gestión de necesidades por refugio';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shelter_need (
            id INT AUTO_INCREMENT NOT NULL,
            shelter_id INT NOT NULL,
            item VARCHAR(255) NOT NULL,
            cantidad_requerida NUMERIC(12, 3) NOT NULL,
            cantidad_recibida NUMERIC(12, 3) NOT NULL,
            prioridad VARCHAR(20) NOT NULL COMMENT \'baja, media, alta, critica\',
            estado VARCHAR(30) NOT NULL COMMENT \'pendiente, parcialmente_satisfecho, satisfecho\',
            notas TEXT DEFAULT NULL,
            fecha_reporte DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            fecha_actualizacion DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_SHELTER_NEED_SHELTER (shelter_id),
            INDEX IDX_SHELTER_NEED_ESTADO (estado),
            INDEX IDX_SHELTER_NEED_PRIORIDAD (prioridad),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE shelter_need ADD CONSTRAINT FK_SHELTER_NEED_SHELTER FOREIGN KEY (shelter_id) REFERENCES shelter (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shelter_need DROP FOREIGN KEY FK_SHELTER_NEED_SHELTER');
        $this->addSql('DROP TABLE shelter_need');
    }
}
