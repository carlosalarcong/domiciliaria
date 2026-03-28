<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea la tabla reset_password_request para recuperación de contraseña';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS reset_password_request (
                id          SERIAL PRIMARY KEY,
                user_id     UUID NOT NULL,
                selector    VARCHAR(20)  NOT NULL,
                hashed_token VARCHAR(100) NOT NULL,
                requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_reset_password_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_reset_password_selector ON reset_password_request (selector)');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS reset_password_request');
    }
}
