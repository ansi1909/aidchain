<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260705170932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agregar tipo de evento OUT_BENEFICIARY al enum';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE inventory_event MODIFY tipo ENUM('out_dispatch', 'out_beneficiary', 'in_reception', 'in_stock') NOT NULL COMMENT '(DC2Type:App\\Enum\\EventType)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE inventory_event MODIFY tipo ENUM('out_dispatch', 'in_reception', 'in_stock') NOT NULL COMMENT '(DC2Type:App\\Enum\\EventType)'");
    }
}
