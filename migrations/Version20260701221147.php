<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migración inicial (Fase 1): crea las tablas organization, shelter,
 * beneficiary, inventory_event y audit_alert, con sus llaves foráneas
 * e índices, tal como están mapeadas en src/Entity/.
 */
final class Version20260701221147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea el esquema inicial del Ledger Humanitario: organization, shelter, beneficiary, inventory_event, audit_alert';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE organization (
                id INT AUTO_INCREMENT NOT NULL,
                nombre VARCHAR(180) NOT NULL,
                tipo VARCHAR(30) NOT NULL,
                public_key LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE shelter (
                id INT AUTO_INCREMENT NOT NULL,
                nombre VARCHAR(180) NOT NULL,
                zona VARCHAR(100) NOT NULL,
                latitud NUMERIC(10, 7) DEFAULT NULL,
                longitud NUMERIC(10, 7) DEFAULT NULL,
                capacidad_censada INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE beneficiary (
                id INT AUTO_INCREMENT NOT NULL,
                shelter_id INT NOT NULL,
                beneficiary_token VARCHAR(128) NOT NULL,
                nombre VARCHAR(180) DEFAULT NULL,
                datos_demograficos JSON DEFAULT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_beneficiary_token (beneficiary_token),
                INDEX IDX_beneficiary_shelter (shelter_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE inventory_event (
                id INT AUTO_INCREMENT NOT NULL,
                beneficiary_id INT DEFAULT NULL,
                shelter_id INT NOT NULL,
                organization_id INT NOT NULL,
                tipo VARCHAR(20) NOT NULL,
                item VARCHAR(120) NOT NULL,
                cantidad NUMERIC(12, 3) NOT NULL,
                unidad VARCHAR(20) NOT NULL,
                hash_actual VARCHAR(64) NOT NULL,
                hash_anterior VARCHAR(64) DEFAULT NULL,
                firma_origen LONGTEXT NOT NULL,
                firma_destino LONGTEXT DEFAULT NULL,
                estado VARCHAR(20) NOT NULL,
                canal_origen VARCHAR(20) NOT NULL,
                lote_id VARCHAR(64) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_beneficiary_fecha (beneficiary_id, created_at),
                INDEX idx_lote (lote_id),
                INDEX IDX_inventory_shelter (shelter_id),
                INDEX IDX_inventory_organization (organization_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE audit_alert (
                id INT AUTO_INCREMENT NOT NULL,
                inventory_event_id INT DEFAULT NULL,
                tipo VARCHAR(60) NOT NULL,
                mensaje LONGTEXT NOT NULL,
                severidad VARCHAR(20) NOT NULL,
                zona_afectada VARCHAR(100) DEFAULT NULL,
                resuelto TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                INDEX IDX_alert_inventory_event (inventory_event_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            ALTER TABLE beneficiary
            ADD CONSTRAINT FK_beneficiary_shelter
            FOREIGN KEY (shelter_id) REFERENCES shelter (id)
        ');

        $this->addSql('
            ALTER TABLE inventory_event
            ADD CONSTRAINT FK_inventory_beneficiary
            FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id)
        ');

        $this->addSql('
            ALTER TABLE inventory_event
            ADD CONSTRAINT FK_inventory_shelter
            FOREIGN KEY (shelter_id) REFERENCES shelter (id)
        ');

        $this->addSql('
            ALTER TABLE inventory_event
            ADD CONSTRAINT FK_inventory_organization
            FOREIGN KEY (organization_id) REFERENCES organization (id)
        ');

        $this->addSql('
            ALTER TABLE audit_alert
            ADD CONSTRAINT FK_alert_inventory_event
            FOREIGN KEY (inventory_event_id) REFERENCES inventory_event (id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_alert DROP FOREIGN KEY FK_alert_inventory_event');
        $this->addSql('ALTER TABLE inventory_event DROP FOREIGN KEY FK_inventory_beneficiary');
        $this->addSql('ALTER TABLE inventory_event DROP FOREIGN KEY FK_inventory_shelter');
        $this->addSql('ALTER TABLE inventory_event DROP FOREIGN KEY FK_inventory_organization');
        $this->addSql('ALTER TABLE beneficiary DROP FOREIGN KEY FK_beneficiary_shelter');

        $this->addSql('DROP TABLE audit_alert');
        $this->addSql('DROP TABLE inventory_event');
        $this->addSql('DROP TABLE beneficiary');
        $this->addSql('DROP TABLE shelter');
        $this->addSql('DROP TABLE organization');
    }
}