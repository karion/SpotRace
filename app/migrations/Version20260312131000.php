<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create parking_spot table for admin management panel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parking_spot (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", name VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE parking_spot');
    }
}
