<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla api_tokens para autenticación de integraciones externas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE api_tokens (
                id          UUID        NOT NULL DEFAULT gen_random_uuid(),
                nombre      VARCHAR(120) NOT NULL,
                token_hash  VARCHAR(64)  NOT NULL,
                permisos    JSON         NOT NULL DEFAULT '[]',
                activo      BOOLEAN      NOT NULL DEFAULT TRUE,
                expires_at  TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                ultimo_uso  TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                creado_en   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_api_token_hash ON api_tokens (token_hash)');
        $this->addSql('CREATE INDEX idx_api_token_activo ON api_tokens (activo)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS api_tokens');
    }
}
