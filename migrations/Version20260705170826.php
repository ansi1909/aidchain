<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260705170826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crear tabla shelter_stock para rastrear stock por refugio e item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shelter_stock (
            id INT AUTO_INCREMENT NOT NULL,
            shelter_id INT NOT NULL,
            item VARCHAR(120) NOT NULL,
            unidad VARCHAR(20) NOT NULL,
            cantidad_disponible NUMERIC(12, 3) NOT NULL,
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_shelter_item (shelter_id, item),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shelter_stock ADD CONSTRAINT FK_SHELTER_STOCK_SHELTER FOREIGN KEY (shelter_id) REFERENCES shelter (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shelter_stock DROP FOREIGN KEY FK_SHELTER_STOCK_SHELTER');
        $this->addSql('DROP TABLE shelter_stock');
    }
}
