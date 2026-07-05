<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Multi-rol por coordinador (Fase 4, refinamiento).
 *
 * Convierte la columna `rol` (un único CoordinatorRole) en `roles` (lista JSON
 * de roles), preservando los datos existentes: cada `rol` se transforma en un
 * array de un elemento. Así una misma persona puede acumular capacidades
 * (p. ej. despachador Y encargado de refugio).
 */
final class Version20260702150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'coordinator.rol (único) → coordinator.roles (lista JSON), preservando datos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coordinator ADD roles JSON DEFAULT NULL');
        $this->addSql('UPDATE coordinator SET roles = JSON_ARRAY(rol)');
        $this->addSql('ALTER TABLE coordinator MODIFY roles JSON NOT NULL');
        $this->addSql('ALTER TABLE coordinator DROP rol');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coordinator ADD rol VARCHAR(30) DEFAULT NULL');
        $this->addSql("UPDATE coordinator SET rol = JSON_UNQUOTE(JSON_EXTRACT(roles, '$[0]'))");
        $this->addSql('ALTER TABLE coordinator MODIFY rol VARCHAR(30) NOT NULL');
        $this->addSql('ALTER TABLE coordinator DROP roles');
    }
}
