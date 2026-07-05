<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crea la tabla item_threshold para el control de doble cobro (Fase 3).
 *
 * Define umbrales máximos de consumo por insumo para evitar que un beneficiario
 * reciba ayuda duplicada por distintos canales.
 */
final class Version20260702170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla item_threshold para umbrales de consumo por insumo (control de doble cobro)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE item_threshold (
            id INT AUTO_INCREMENT NOT NULL,
            item VARCHAR(255) NOT NULL,
            cantidad_maxima NUMERIC(12, 3) NOT NULL,
            unidad VARCHAR(20) NOT NULL,
            ventana_horas INT NOT NULL DEFAULT 24,
            descripcion TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_item_threshold_item (item),
            INDEX IDX_item_threshold_item (item),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE item_threshold');
    }
}
