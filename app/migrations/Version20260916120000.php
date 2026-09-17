<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parking locations with optional links for existing parking spots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parking_location (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", name VARCHAR(120) NOT NULL, UNIQUE INDEX uniq_parking_location_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE parking_spot ADD location_id CHAR(36) DEFAULT NULL COMMENT "(DC2Type:guid)"');
        $this->addSql('CREATE INDEX IDX_PARKING_SPOT_LOCATION ON parking_spot (location_id)');
        $this->addSql('ALTER TABLE parking_spot ADD CONSTRAINT FK_PARKING_SPOT_LOCATION FOREIGN KEY (location_id) REFERENCES parking_location (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking_spot DROP FOREIGN KEY FK_PARKING_SPOT_LOCATION');
        $this->addSql('DROP INDEX IDX_PARKING_SPOT_LOCATION ON parking_spot');
        $this->addSql('ALTER TABLE parking_spot DROP location_id');
        $this->addSql('DROP TABLE parking_location');
    }
}
