<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create parking spot assignments and reservations tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parking_spot_assignment (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", parking_spot_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", assigned_user_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", assigned_by_user_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", starts_at DATE NOT NULL COMMENT "(DC2Type:date_immutable)", ends_at DATE DEFAULT NULL COMMENT "(DC2Type:date_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_ASSIGNMENT_SPOT (parking_spot_id), INDEX IDX_ASSIGNMENT_USER (assigned_user_id), INDEX IDX_ASSIGNMENT_BY (assigned_by_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE parking_spot_assignment ADD CONSTRAINT FK_ASSIGNMENT_SPOT FOREIGN KEY (parking_spot_id) REFERENCES parking_spot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parking_spot_assignment ADD CONSTRAINT FK_ASSIGNMENT_USER FOREIGN KEY (assigned_user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parking_spot_assignment ADD CONSTRAINT FK_ASSIGNMENT_BY FOREIGN KEY (assigned_by_user_id) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE parking_reservation (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", parking_spot_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", reserved_for_user_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", created_by_user_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", reservation_date DATE NOT NULL COMMENT "(DC2Type:date_immutable)", type VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_RESERVATION_SPOT (parking_spot_id), INDEX IDX_RESERVATION_USER (reserved_for_user_id), INDEX IDX_RESERVATION_BY (created_by_user_id), UNIQUE INDEX uniq_spot_per_day (parking_spot_id, reservation_date), UNIQUE INDEX uniq_user_per_day (reserved_for_user_id, reservation_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE parking_reservation ADD CONSTRAINT FK_RESERVATION_SPOT FOREIGN KEY (parking_spot_id) REFERENCES parking_spot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parking_reservation ADD CONSTRAINT FK_RESERVATION_USER FOREIGN KEY (reserved_for_user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parking_reservation ADD CONSTRAINT FK_RESERVATION_BY FOREIGN KEY (created_by_user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking_reservation DROP FOREIGN KEY FK_RESERVATION_SPOT');
        $this->addSql('ALTER TABLE parking_reservation DROP FOREIGN KEY FK_RESERVATION_USER');
        $this->addSql('ALTER TABLE parking_reservation DROP FOREIGN KEY FK_RESERVATION_BY');
        $this->addSql('DROP TABLE parking_reservation');

        $this->addSql('ALTER TABLE parking_spot_assignment DROP FOREIGN KEY FK_ASSIGNMENT_SPOT');
        $this->addSql('ALTER TABLE parking_spot_assignment DROP FOREIGN KEY FK_ASSIGNMENT_USER');
        $this->addSql('ALTER TABLE parking_spot_assignment DROP FOREIGN KEY FK_ASSIGNMENT_BY');
        $this->addSql('DROP TABLE parking_spot_assignment');
    }
}
