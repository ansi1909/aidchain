<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añade relación opcional Shelter → Organization (modelo híbrido).
 *
 * Un refugio puede tener una organización creadora/gestora (atribución),
 * pero no es propiedad exclusiva: varias organizaciones pueden operar
 * en el mismo refugio. La FK es nullable para permitir refugios sin org.
 */
final class Version20260702153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade organization_id (nullable) a shelter para atribución opcional';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shelter ADD organization_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE shelter ADD CONSTRAINT FK_SHELTER_ORGANIZATION FOREIGN KEY (organization_id) REFERENCES organization (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shelter DROP FOREIGN KEY FK_SHELTER_ORGANIZATION');
        $this->addSql('ALTER TABLE shelter DROP organization_id');
    }
}
