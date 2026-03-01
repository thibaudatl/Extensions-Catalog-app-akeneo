<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pim_token table for encrypted token persistence';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pim_token (
            pim_url VARCHAR(500) NOT NULL,
            access_token VARCHAR(1024) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(pim_url)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_token');
    }
}
