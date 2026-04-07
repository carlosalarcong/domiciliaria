<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407042940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega columnas 2FA (totp_secret, backup_codes, two_factor_enabled) a la tabla users de la BD principal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE users ADD COLUMN IF NOT EXISTS backup_codes JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS totp_secret');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS backup_codes');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS two_factor_enabled');
    }
}
