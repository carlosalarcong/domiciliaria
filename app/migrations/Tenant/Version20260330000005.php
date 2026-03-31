<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tablas webhook_suscripciones y webhook_deliveries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE webhook_suscripciones (
            id UUID NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            url VARCHAR(500) NOT NULL,
            secret VARCHAR(64) NOT NULL,
            eventos JSON NOT NULL,
            activo BOOLEAN NOT NULL DEFAULT true,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            actualizado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN webhook_suscripciones.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN webhook_suscripciones.creado_en IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN webhook_suscripciones.actualizado_en IS '(DC2Type:datetime_immutable)'");

        $this->addSql("CREATE TABLE webhook_deliveries (
            id UUID NOT NULL,
            suscripcion_id UUID NOT NULL,
            evento VARCHAR(60) NOT NULL,
            payload JSON NOT NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            intentos INT NOT NULL DEFAULT 0,
            codigo_respuesta INT DEFAULT NULL,
            respuesta_body TEXT DEFAULT NULL,
            ultimo_intento TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("COMMENT ON COLUMN webhook_deliveries.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN webhook_deliveries.suscripcion_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN webhook_deliveries.ultimo_intento IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN webhook_deliveries.creado_en IS '(DC2Type:datetime_immutable)'");

        $this->addSql("CREATE INDEX idx_wh_delivery_suscripcion ON webhook_deliveries (suscripcion_id, creado_en)");
        $this->addSql("CREATE INDEX idx_wh_delivery_estado ON webhook_deliveries (estado)");

        $this->addSql("ALTER TABLE webhook_deliveries
            ADD CONSTRAINT fk_wh_delivery_suscripcion
            FOREIGN KEY (suscripcion_id)
            REFERENCES webhook_suscripciones(id)
            ON DELETE CASCADE
            NOT DEFERRABLE INITIALLY IMMEDIATE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webhook_deliveries DROP CONSTRAINT fk_wh_delivery_suscripcion');
        $this->addSql('DROP TABLE webhook_deliveries');
        $this->addSql('DROP TABLE webhook_suscripciones');
    }
}
