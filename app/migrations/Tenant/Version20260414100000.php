<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega columnas de control de integraciones a configuracion_clinica';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE configuracion_clinica ADD COLUMN IF NOT EXISTS integracion_api_activa BOOLEAN NOT NULL DEFAULT TRUE");
        $this->addSql("ALTER TABLE configuracion_clinica ADD COLUMN IF NOT EXISTS integracion_webhooks_activa BOOLEAN NOT NULL DEFAULT TRUE");
        $this->addSql("ALTER TABLE configuracion_clinica ADD COLUMN IF NOT EXISTS integracion_sincronizacion_activa BOOLEAN NOT NULL DEFAULT FALSE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE configuracion_clinica DROP COLUMN IF EXISTS integracion_api_activa');
        $this->addSql('ALTER TABLE configuracion_clinica DROP COLUMN IF EXISTS integracion_webhooks_activa');
        $this->addSql('ALTER TABLE configuracion_clinica DROP COLUMN IF EXISTS integracion_sincronizacion_activa');
    }
}
