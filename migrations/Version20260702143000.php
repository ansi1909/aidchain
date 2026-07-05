<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 4 — Firma cruzada de doble vía.
 *
 * Añade `coordinator_destino_id` a inventory_event: el coordinador
 * (ENCARGADO_REFUGIO) que confirma y firma la recepción en destino, cuya clave
 * pública valida `firma_destino` al consolidar el evento (EN_TRANSITO → CONSOLIDADO).
 */
final class Version20260702143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 4: coordinator_destino_id en inventory_event (firma cruzada de recepción)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_event ADD coordinator_destino_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_event ADD CONSTRAINT FK_E732A1A7B3E1BEDB FOREIGN KEY (coordinator_destino_id) REFERENCES coordinator (id)');
        $this->addSql('CREATE INDEX IDX_E732A1A7B3E1BEDB ON inventory_event (coordinator_destino_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_event DROP FOREIGN KEY FK_E732A1A7B3E1BEDB');
        $this->addSql('DROP INDEX IDX_E732A1A7B3E1BEDB ON inventory_event');
        $this->addSql('ALTER TABLE inventory_event DROP coordinator_destino_id');
    }
}
