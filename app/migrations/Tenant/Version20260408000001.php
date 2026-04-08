<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla tarifas para configuración de valores por tipo de concepto';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tarifas (
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
        $this->addSql('CREATE INDEX IDX_7A6F316D8877858D ON tarifas (mandante_id)');
        $this->addSql('CREATE INDEX idx_tarifa_lookup ON tarifas (mandante_id, tipo_concepto, vigencia_desde)');
        $this->addSql('ALTER TABLE tarifas ADD CONSTRAINT FK_7A6F316D8877858D FOREIGN KEY (mandante_id) REFERENCES mandantes (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tarifas DROP CONSTRAINT FK_7A6F316D8877858D');
        $this->addSql('DROP TABLE tarifas');
    }
}
