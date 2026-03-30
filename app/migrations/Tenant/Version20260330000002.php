<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega turnos_descubiertos, monto_descuento y descripcion_descuento a facturas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE facturas ADD COLUMN IF NOT EXISTS turnos_descubiertos INT NOT NULL DEFAULT 0");
        $this->addSql("ALTER TABLE facturas ADD COLUMN IF NOT EXISTS monto_descuento NUMERIC(12,2) NOT NULL DEFAULT '0.00'");
        $this->addSql("ALTER TABLE facturas ADD COLUMN IF NOT EXISTS descripcion_descuento VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE facturas DROP COLUMN IF EXISTS turnos_descubiertos");
        $this->addSql("ALTER TABLE facturas DROP COLUMN IF EXISTS monto_descuento");
        $this->addSql("ALTER TABLE facturas DROP COLUMN IF EXISTS descripcion_descuento");
    }
}
