<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla tarifas en BD principal (espejo de migración tenant)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS tarifas (
            id UUID NOT NULL,
            mandante_id UUID DEFAULT NULL,
            tipo_concepto VARCHAR(255) NOT NULL,
            valor_unitario NUMERIC(10, 2) NOT NULL,
            vigencia_desde DATE NOT NULL,
            vigencia_hasta DATE DEFAULT NULL,
            activa BOOLEAN NOT NULL DEFAULT TRUE,
            descripcion VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_7A6F316D8877858D ON tarifas (mandante_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_tarifa_lookup ON tarifas (mandante_id, tipo_concepto, vigencia_desde)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tarifas');
    }
}
