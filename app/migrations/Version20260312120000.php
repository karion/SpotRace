<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user table for authentication with UUID primary key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", email VARCHAR(180) NOT NULL, name VARCHAR(120) NOT NULL, password_hash VARCHAR(255) NOT NULL, roles JSON NOT NULL, email_verified_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", email_verification_token VARCHAR(100) DEFAULT NULL, password_reset_token VARCHAR(100) DEFAULT NULL, password_reset_expires_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX uniq_user_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user');
    }
}
