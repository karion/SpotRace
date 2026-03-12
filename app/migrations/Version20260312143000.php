<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user status field for blocking and enforced password reset states';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD status VARCHAR(40) NOT NULL DEFAULT 'active'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP status');
    }
}
