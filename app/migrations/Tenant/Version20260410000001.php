<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla configuracion_clinica con parámetros generales por tenant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS configuracion_clinica (
                id UUID NOT NULL,
                nombre_clinica VARCHAR(150) NOT NULL DEFAULT 'Mi Clínica',
                razon_social VARCHAR(200) DEFAULT NULL,
                rut_empresa VARCHAR(20) DEFAULT NULL,
                giro VARCHAR(150) DEFAULT NULL,
                direccion_fiscal VARCHAR(250) DEFAULT NULL,
                telefono_contacto VARCHAR(20) DEFAULT NULL,
                email_contacto VARCHAR(180) DEFAULT NULL,
                porcentaje_iva VARCHAR(5) NOT NULL DEFAULT '19.00',
                dias_vencimiento_factura INT NOT NULL DEFAULT 30,
                prefijo_factura VARCHAR(10) NOT NULL DEFAULT '',
                dias_anticipacion_alertas INT NOT NULL DEFAULT 2,
                hora_revision_turnos VARCHAR(5) NOT NULL DEFAULT '08:00',
                limite_documentos_mb INT NOT NULL DEFAULT 10,
                notif_turno_descubierto BOOLEAN NOT NULL DEFAULT TRUE,
                notif_evento_grave BOOLEAN NOT NULL DEFAULT TRUE,
                email_notificaciones VARCHAR(180) DEFAULT NULL,
                modulo_finanzas_activo BOOLEAN NOT NULL DEFAULT TRUE,
                modulo_eventos_activo BOOLEAN NOT NULL DEFAULT TRUE,
                extras JSONB NOT NULL DEFAULT '{}',
                PRIMARY KEY (id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS configuracion_clinica');
    }
}
