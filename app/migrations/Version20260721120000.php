<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add global and company-level settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_setting (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", setting_key VARCHAR(120) NOT NULL, type VARCHAR(40) NOT NULL, value JSON NOT NULL, label VARCHAR(160) NOT NULL, description LONGTEXT DEFAULT NULL, setting_group VARCHAR(80) NOT NULL, sort_order INT NOT NULL, UNIQUE INDEX uniq_app_setting_key (setting_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE company_setting (id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", company_id CHAR(36) NOT NULL COMMENT "(DC2Type:guid)", setting_key VARCHAR(120) NOT NULL, value JSON NOT NULL, INDEX IDX_COMPANY_SETTING_COMPANY (company_id), UNIQUE INDEX uniq_company_setting_key (company_id, setting_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE company_setting ADD CONSTRAINT FK_COMPANY_SETTING_COMPANY FOREIGN KEY (company_id) REFERENCES company (id) ON DELETE CASCADE');

        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111111', 'registration.allowed_email_domains', 'string_list', [], 'Dozwolone domeny email', 'Lista domen oddzielona przecinkami. Pusta lista pozwala na dowolną domenę.', 'Rejestracja', 10));
        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111112', 'registration.password_min_length', 'int', 12, 'Minimalna długość hasła', null, 'Rejestracja', 20));
        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111113', 'registration.password_require_lowercase', 'bool', false, 'Wymagaj małej litery', null, 'Rejestracja', 30));
        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111114', 'registration.password_require_uppercase', 'bool', false, 'Wymagaj wielkiej litery', null, 'Rejestracja', 40));
        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111115', 'registration.password_require_digit', 'bool', false, 'Wymagaj cyfry', null, 'Rejestracja', 50));
        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111116', 'registration.password_require_special', 'bool', false, 'Wymagaj znaku specjalnego', null, 'Rejestracja', 60));
        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111117', 'registration.token_ttl_hours', 'int', 48, 'Ważność linku rejestracyjnego w godzinach', null, 'Rejestracja', 70));
        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111118', 'reservation.confirmation_deadline_hour', 'int', 7, 'Godzina graniczna przypisanego miejsca', 'Do tej godziny przypisane miejsce jest zablokowane dla innych osób.', 'Rezerwacje', 10));
        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111119', 'reservation.assigned_window_days', 'int', 7, 'Okno przypisanych miejsc w dniach', null, 'Rezerwacje', 20));
        $this->addSql($this->insertSetting('11111111-1111-4111-8111-111111111120', 'reservation.free_window_days', 'int', 1, 'Okno wolnych miejsc w dniach', null, 'Rezerwacje', 30));

        $this->addSql('ALTER TABLE company DROP allowed_email_domains, DROP password_min_length, DROP password_require_lowercase, DROP password_require_uppercase, DROP password_require_digit, DROP password_require_special');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company ADD allowed_email_domains LONGTEXT DEFAULT NULL, ADD password_min_length INT NOT NULL DEFAULT 12, ADD password_require_lowercase TINYINT(1) NOT NULL DEFAULT 0, ADD password_require_uppercase TINYINT(1) NOT NULL DEFAULT 0, ADD password_require_digit TINYINT(1) NOT NULL DEFAULT 0, ADD password_require_special TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE company_setting DROP FOREIGN KEY FK_COMPANY_SETTING_COMPANY');
        $this->addSql('DROP TABLE company_setting');
        $this->addSql('DROP TABLE app_setting');
    }

    private function insertSetting(string $id, string $key, string $type, mixed $value, string $label, ?string $description, string $group, int $sortOrder): string
    {
        return sprintf(
            "INSERT INTO app_setting (id, setting_key, type, value, label, description, setting_group, sort_order) VALUES ('%s', '%s', '%s', '%s', '%s', %s, '%s', %d)",
            $id,
            $key,
            $type,
            json_encode($value, JSON_THROW_ON_ERROR),
            str_replace("'", "''", $label),
            null === $description ? 'NULL' : "'".str_replace("'", "''", $description)."'",
            str_replace("'", "''", $group),
            $sortOrder,
        );
    }
}
