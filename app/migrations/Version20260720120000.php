<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add companies, company registration tokens, and temporal company parking spot assignments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", name VARCHAR(160) NOT NULL, slug VARCHAR(160) NOT NULL, status VARCHAR(40) NOT NULL, allowed_email_domains LONGTEXT DEFAULT NULL, password_min_length INT NOT NULL, password_require_lowercase TINYINT(1) NOT NULL, password_require_uppercase TINYINT(1) NOT NULL, password_require_digit TINYINT(1) NOT NULL, password_require_special TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX uniq_company_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE company_registration_token (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", company_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", token VARCHAR(100) NOT NULL, expires_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", revoked_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_COMPANY_REGISTRATION_TOKEN_COMPANY (company_id), UNIQUE INDEX uniq_company_registration_token (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE company_parking_spot (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", company_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", parking_spot_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", starts_at DATE NOT NULL COMMENT "(DC2Type:date_immutable)", ends_at DATE DEFAULT NULL COMMENT "(DC2Type:date_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_COMPANY_SPOT_COMPANY (company_id), INDEX IDX_COMPANY_SPOT_SPOT (parking_spot_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE company_registration_token ADD CONSTRAINT FK_COMPANY_REGISTRATION_TOKEN_COMPANY FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_parking_spot ADD CONSTRAINT FK_COMPANY_SPOT_COMPANY FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_parking_spot ADD CONSTRAINT FK_COMPANY_SPOT_SPOT FOREIGN KEY (parking_spot_id) REFERENCES parking_spot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD company_id CHAR(36) DEFAULT NULL COMMENT "(DC2Type:guid)"');
        $this->addSql('CREATE INDEX IDX_USER_COMPANY ON user (company_id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_USER_COMPANY FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_USER_COMPANY');
        $this->addSql('DROP INDEX IDX_USER_COMPANY ON user');
        $this->addSql('ALTER TABLE user DROP company_id');
        $this->addSql('ALTER TABLE company_registration_token DROP FOREIGN KEY FK_COMPANY_REGISTRATION_TOKEN_COMPANY');
        $this->addSql('ALTER TABLE company_parking_spot DROP FOREIGN KEY FK_COMPANY_SPOT_COMPANY');
        $this->addSql('ALTER TABLE company_parking_spot DROP FOREIGN KEY FK_COMPANY_SPOT_SPOT');
        $this->addSql('DROP TABLE company_registration_token');
        $this->addSql('DROP TABLE company_parking_spot');
        $this->addSql('DROP TABLE company');
    }
}
