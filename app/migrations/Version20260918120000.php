<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vehicle registry and license plate snapshots to reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vehicle (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", owner_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", license_plate VARCHAR(20) NOT NULL, INDEX IDX_VEHICLE_OWNER (owner_id), UNIQUE INDEX uniq_vehicle_owner_plate (owner_id, license_plate), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE vehicle ADD CONSTRAINT FK_VEHICLE_OWNER FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parking_reservation ADD license_plate LONGTEXT DEFAULT NULL');
        $this->addSql("INSERT INTO app_setting (id, setting_key, type, value, label, description, setting_group, sort_order) VALUES ('11111111-1111-4111-8111-111111111121', 'reservation.require_license_plate', 'bool', 'false', 'Wymagaj numeru rejestracyjnego', 'Jeśli włączone, każda nowa rezerwacja wymaga podania tablicy rejestracyjnej.', 'Rezerwacje', 40)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_setting WHERE setting_key = 'reservation.require_license_plate'");
        $this->addSql('ALTER TABLE parking_reservation DROP license_plate');
        $this->addSql('ALTER TABLE vehicle DROP FOREIGN KEY FK_VEHICLE_OWNER');
        $this->addSql('DROP TABLE vehicle');
    }
}
