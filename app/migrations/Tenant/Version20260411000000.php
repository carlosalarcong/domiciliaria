<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schema completo tenant: todas las tablas con columnas finales incorporadas';
    }

    public function up(Schema $schema): void
    {
        // ── users ─────────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE users (
            id UUID NOT NULL,
            email VARCHAR(180) NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            activo BOOLEAN NOT NULL,
            totp_secret VARCHAR(255) DEFAULT NULL,
            backup_codes JSON NOT NULL DEFAULT \'[]\',
            two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE,
            tours_completados JSON NOT NULL DEFAULT \'[]\',
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            actualizado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');

        // ── mandantes ─────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE mandantes (
            id UUID NOT NULL,
            nombre VARCHAR(200) NOT NULL,
            rut VARCHAR(20) NOT NULL,
            contacto VARCHAR(150) DEFAULT NULL,
            email VARCHAR(180) DEFAULT NULL,
            telefono VARCHAR(20) DEFAULT NULL,
            activo BOOLEAN NOT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            actualizado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E25D09A9AD145DBA ON mandantes (rut)');

        // ── pacientes ─────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE pacientes (
            id UUID NOT NULL,
            nombres VARCHAR(150) NOT NULL,
            apellidos VARCHAR(150) NOT NULL,
            rut TEXT DEFAULT NULL,
            rut_hash VARCHAR(64) DEFAULT NULL,
            fecha_nacimiento DATE DEFAULT NULL,
            direccion VARCHAR(255) DEFAULT NULL,
            comuna VARCHAR(100) DEFAULT NULL,
            region VARCHAR(100) DEFAULT NULL,
            telefono VARCHAR(20) DEFAULT NULL,
            telefono_emergencia VARCHAR(20) DEFAULT NULL,
            tipo_servicio VARCHAR(255) NOT NULL,
            estado VARCHAR(255) NOT NULL,
            fecha_ingreso DATE DEFAULT NULL,
            fecha_termino DATE DEFAULT NULL,
            tutor_nombre VARCHAR(200) DEFAULT NULL,
            tutor_telefono VARCHAR(20) DEFAULT NULL,
            tutor_relacion VARCHAR(100) DEFAULT NULL,
            observaciones TEXT DEFAULT NULL,
            diagnosticos TEXT DEFAULT NULL,
            medicamentos TEXT DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            actualizado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            mandante_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_pacientes_rut_hash ON pacientes (rut_hash) WHERE rut_hash IS NOT NULL');
        $this->addSql('CREATE INDEX IDX_971B78518877858D ON pacientes (mandante_id)');

        // ── trabajadores ──────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE trabajadores (
            id UUID NOT NULL,
            nombres VARCHAR(150) NOT NULL,
            apellidos VARCHAR(150) NOT NULL,
            rut TEXT DEFAULT NULL,
            rut_hash VARCHAR(64) DEFAULT NULL,
            perfil VARCHAR(255) NOT NULL,
            telefono VARCHAR(20) DEFAULT NULL,
            email VARCHAR(180) DEFAULT NULL,
            direccion VARCHAR(255) DEFAULT NULL,
            estado VARCHAR(255) NOT NULL,
            fecha_ingreso DATE DEFAULT NULL,
            fecha_salida DATE DEFAULT NULL,
            cuenta_bancaria TEXT DEFAULT NULL,
            datos_previsionales TEXT DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            actualizado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            user_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_trabajadores_rut_hash ON trabajadores (rut_hash) WHERE rut_hash IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CF4C0B49A76ED395 ON trabajadores (user_id)');

        // ── turnos ────────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE turnos (
            id UUID NOT NULL,
            fecha DATE NOT NULL,
            hora_inicio TIME(0) WITHOUT TIME ZONE NOT NULL,
            hora_termino TIME(0) WITHOUT TIME ZONE NOT NULL,
            tipo_turno VARCHAR(255) NOT NULL,
            estado VARCHAR(255) NOT NULL,
            es_reemplazo BOOLEAN NOT NULL,
            motivo_reemplazo VARCHAR(255) DEFAULT NULL,
            registro_inicio TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            registro_termino TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            observaciones TEXT DEFAULT NULL,
            ultima_alerta_descubierto_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            actualizado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            paciente_id UUID NOT NULL,
            trabajador_id UUID DEFAULT NULL,
            turno_original_id UUID DEFAULT NULL,
            trabajador_original_id UUID DEFAULT NULL,
            creado_por_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_B85558187310DAD4 ON turnos (paciente_id)');
        $this->addSql('CREATE INDEX IDX_B8555818EC3656E ON turnos (trabajador_id)');
        $this->addSql('CREATE INDEX IDX_B85558188E90C9F2 ON turnos (turno_original_id)');
        $this->addSql('CREATE INDEX idx_turnos_trabajador_original_id ON turnos (trabajador_original_id)');
        $this->addSql('CREATE INDEX IDX_B8555818FE35D8C4 ON turnos (creado_por_id)');

        // ── liquidaciones_mensuales ───────────────────────────────────────────
        $this->addSql('CREATE TABLE liquidaciones_mensuales (
            id UUID NOT NULL,
            anio SMALLINT NOT NULL,
            mes SMALLINT NOT NULL,
            total_turnos INT NOT NULL,
            total_horas NUMERIC(8, 2) NOT NULL,
            monto_total NUMERIC(12, 2) NOT NULL,
            estado VARCHAR(255) NOT NULL,
            observaciones TEXT DEFAULT NULL,
            fecha_pago DATE DEFAULT NULL,
            motivo_anulacion TEXT DEFAULT NULL,
            fue_pagada BOOLEAN NOT NULL DEFAULT FALSE,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            trabajador_id UUID NOT NULL,
            creado_por_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_B32C50FEEC3656E ON liquidaciones_mensuales (trabajador_id)');
        $this->addSql('CREATE INDEX IDX_B32C50FEFE35D8C4 ON liquidaciones_mensuales (creado_por_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B32C50FEEC3656E89A505776EC83E05 ON liquidaciones_mensuales (trabajador_id, anio, mes)');

        // ── items_liquidacion ─────────────────────────────────────────────────
        $this->addSql('CREATE TABLE items_liquidacion (
            id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
            concepto VARCHAR(255) NOT NULL,
            descripcion TEXT DEFAULT NULL,
            cantidad NUMERIC(8, 2) NOT NULL,
            valor_unitario NUMERIC(10, 2) NOT NULL,
            subtotal NUMERIC(12, 2) NOT NULL,
            liquidacion_id UUID NOT NULL,
            turno_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_A813E608326DAF0C ON items_liquidacion (liquidacion_id)');
        $this->addSql('CREATE INDEX IDX_A813E60869C5211E ON items_liquidacion (turno_id)');

        // ── facturas ──────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE facturas (
            id UUID NOT NULL,
            numero_factura VARCHAR(30) DEFAULT NULL,
            anio SMALLINT NOT NULL,
            mes SMALLINT NOT NULL,
            total_turnos INT NOT NULL,
            turnos_descubiertos INT NOT NULL DEFAULT 0,
            monto_neto NUMERIC(12, 2) NOT NULL,
            porcentaje_iva NUMERIC(5, 2) NOT NULL,
            monto_iva NUMERIC(12, 2) NOT NULL,
            monto_total NUMERIC(12, 2) NOT NULL,
            monto_descuento NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
            descripcion_descuento VARCHAR(255) DEFAULT NULL,
            estado VARCHAR(255) NOT NULL,
            fecha_emision DATE DEFAULT NULL,
            fecha_vencimiento DATE DEFAULT NULL,
            fecha_pago DATE DEFAULT NULL,
            observaciones TEXT DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            mandante_id UUID NOT NULL,
            creado_por_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_622B9C0F8877858D ON facturas (mandante_id)');
        $this->addSql('CREATE INDEX IDX_622B9C0FFE35D8C4 ON facturas (creado_por_id)');

        // ── bitacora_operativa ────────────────────────────────────────────────
        $this->addSql('CREATE TABLE bitacora_operativa (
            id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
            fecha TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            tipo VARCHAR(255) NOT NULL,
            descripcion TEXT NOT NULL,
            paciente_id UUID NOT NULL,
            creado_por_id UUID NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_5BF9F65D7310DAD4 ON bitacora_operativa (paciente_id)');
        $this->addSql('CREATE INDEX IDX_5BF9F65DFE35D8C4 ON bitacora_operativa (creado_por_id)');

        // ── condicion_domicilio ───────────────────────────────────────────────
        $this->addSql('CREATE TABLE condicion_domicilio (
            id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
            acceso_descripcion TEXT DEFAULT NULL,
            tiene_mascotas BOOLEAN NOT NULL,
            mascotas_detalle VARCHAR(255) DEFAULT NULL,
            tiene_barreras_arquitectonicas BOOLEAN NOT NULL,
            barreras_detalle TEXT DEFAULT NULL,
            observaciones_seguridad TEXT DEFAULT NULL,
            requiere_ascensor BOOLEAN NOT NULL,
            codigo_acceso VARCHAR(10) DEFAULT NULL,
            actualizado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            paciente_id UUID NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FCA7566F7310DAD4 ON condicion_domicilio (paciente_id)');

        // ── disponibilidad_trabajador ─────────────────────────────────────────
        $this->addSql('CREATE TABLE disponibilidad_trabajador (
            id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
            fecha DATE NOT NULL,
            hora_desde TIME(0) WITHOUT TIME ZONE DEFAULT NULL,
            hora_hasta TIME(0) WITHOUT TIME ZONE DEFAULT NULL,
            disponible BOOLEAN NOT NULL,
            observacion VARCHAR(255) DEFAULT NULL,
            trabajador_id UUID NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_D838D311EC3656E ON disponibilidad_trabajador (trabajador_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D838D311EC3656E1A8B7D9 ON disponibilidad_trabajador (trabajador_id, fecha)');

        // ── documentos_trabajador ─────────────────────────────────────────────
        $this->addSql('CREATE TABLE documentos_trabajador (
            id UUID NOT NULL,
            tipo VARCHAR(255) NOT NULL,
            nombre_original VARCHAR(255) NOT NULL,
            ruta_archivo VARCHAR(255) NOT NULL,
            extension VARCHAR(20) NOT NULL,
            tamano_bytes INT NOT NULL,
            descripcion VARCHAR(500) DEFAULT NULL,
            fecha_vencimiento DATE DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            trabajador_id UUID NOT NULL,
            subido_por_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_C844647EEC3656E ON documentos_trabajador (trabajador_id)');
        $this->addSql('CREATE INDEX IDX_C844647E446C2E6 ON documentos_trabajador (subido_por_id)');

        // ── eventos_adversos ──────────────────────────────────────────────────
        $this->addSql('CREATE TABLE eventos_adversos (
            id UUID NOT NULL,
            fecha_evento DATE NOT NULL,
            hora_evento TIME(0) WITHOUT TIME ZONE DEFAULT NULL,
            tipo VARCHAR(255) NOT NULL,
            gravedad VARCHAR(255) NOT NULL,
            estado VARCHAR(255) NOT NULL,
            descripcion TEXT NOT NULL,
            acciones_tomadas TEXT DEFAULT NULL,
            notificado_familia BOOLEAN NOT NULL,
            notificado_medico BOOLEAN NOT NULL,
            fecha_cierre DATE DEFAULT NULL,
            observacion_cierre TEXT DEFAULT NULL,
            responsable_id UUID DEFAULT NULL,
            revisado_por_id UUID DEFAULT NULL,
            revisado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            cerrado_por_id UUID DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            actualizado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            paciente_id UUID NOT NULL,
            trabajador_id UUID DEFAULT NULL,
            creado_por_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_70627DBB7310DAD4 ON eventos_adversos (paciente_id)');
        $this->addSql('CREATE INDEX IDX_70627DBBEC3656E ON eventos_adversos (trabajador_id)');
        $this->addSql('CREATE INDEX IDX_70627DBBFE35D8C4 ON eventos_adversos (creado_por_id)');
        $this->addSql('CREATE INDEX idx_evento_responsable ON eventos_adversos (responsable_id)');

        // ── historial_comunicacion ────────────────────────────────────────────
        $this->addSql('CREATE TABLE historial_comunicacion (
            id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
            tipo VARCHAR(255) NOT NULL,
            contacto VARCHAR(200) DEFAULT NULL,
            descripcion TEXT NOT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            paciente_id UUID NOT NULL,
            creado_por_id UUID NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_982C73027310DAD4 ON historial_comunicacion (paciente_id)');
        $this->addSql('CREATE INDEX IDX_982C7302FE35D8C4 ON historial_comunicacion (creado_por_id)');

        // ── seguimientos_evento ───────────────────────────────────────────────
        $this->addSql('CREATE TABLE seguimientos_evento (
            id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
            nota TEXT NOT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            evento_id UUID NOT NULL,
            creado_por_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_BDC9177F87A5F842 ON seguimientos_evento (evento_id)');
        $this->addSql('CREATE INDEX IDX_BDC9177FFE35D8C4 ON seguimientos_evento (creado_por_id)');

        // ── ext_log_entries ───────────────────────────────────────────────────
        $this->addSql('CREATE TABLE ext_log_entries (
            id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
            action VARCHAR(8) NOT NULL,
            logged_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            object_id VARCHAR(64) DEFAULT NULL,
            object_class VARCHAR(255) NOT NULL,
            version INT NOT NULL,
            data JSON DEFAULT NULL,
            username VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX log_class_lookup_idx ON ext_log_entries (object_class)');
        $this->addSql('CREATE INDEX log_date_lookup_idx ON ext_log_entries (logged_at)');
        $this->addSql('CREATE INDEX log_user_lookup_idx ON ext_log_entries (username)');
        $this->addSql('CREATE INDEX log_version_lookup_idx ON ext_log_entries (object_id, object_class, version)');

        // ── reset_password_request ────────────────────────────────────────────
        $this->addSql('CREATE TABLE reset_password_request (
            id SERIAL PRIMARY KEY,
            user_id UUID NOT NULL,
            selector VARCHAR(20) NOT NULL,
            hashed_token VARCHAR(100) NOT NULL,
            requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');
        $this->addSql('CREATE INDEX idx_reset_password_selector ON reset_password_request (selector)');
        $this->addSql("COMMENT ON COLUMN reset_password_request.requested_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN reset_password_request.expires_at IS '(DC2Type:datetime_immutable)'");

        // ── audit_logs ────────────────────────────────────────────────────────
        $this->addSql("CREATE TABLE audit_logs (
            id SERIAL PRIMARY KEY,
            evento VARCHAR(50) NOT NULL,
            email_intentado VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            tenant VARCHAR(100) DEFAULT NULL,
            detalles JSON NOT NULL DEFAULT '{}',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
        )");
        $this->addSql('CREATE INDEX idx_audit_ip ON audit_logs (ip_address)');
        $this->addSql('CREATE INDEX idx_audit_created ON audit_logs (created_at)');

        // ── api_tokens ────────────────────────────────────────────────────────
        $this->addSql("CREATE TABLE api_tokens (
            id UUID NOT NULL DEFAULT gen_random_uuid(),
            nombre VARCHAR(120) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            permisos JSON NOT NULL DEFAULT '[]',
            activo BOOLEAN NOT NULL DEFAULT TRUE,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            ultimo_uso TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )");
        $this->addSql('CREATE UNIQUE INDEX uniq_api_token_hash ON api_tokens (token_hash)');
        $this->addSql('CREATE INDEX idx_api_token_activo ON api_tokens (activo)');

        // ── notificaciones ────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE notificaciones (
            id UUID NOT NULL,
            destinatario_id UUID NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            cuerpo TEXT DEFAULT NULL,
            url VARCHAR(500) DEFAULT NULL,
            leida_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql("COMMENT ON COLUMN notificaciones.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN notificaciones.destinatario_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN notificaciones.leida_en IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN notificaciones.creado_en IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_notif_dest_leida ON notificaciones (destinatario_id, leida_en)');

        // ── webhook_suscripciones ─────────────────────────────────────────────
        $this->addSql("CREATE TABLE webhook_suscripciones (
            id UUID NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            url VARCHAR(500) NOT NULL,
            secret VARCHAR(64) NOT NULL,
            eventos JSON NOT NULL,
            activo BOOLEAN NOT NULL DEFAULT TRUE,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            actualizado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id)
        )");
        $this->addSql("COMMENT ON COLUMN webhook_suscripciones.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN webhook_suscripciones.creado_en IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN webhook_suscripciones.actualizado_en IS '(DC2Type:datetime_immutable)'");

        // ── webhook_deliveries ────────────────────────────────────────────────
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
            PRIMARY KEY (id)
        )");
        $this->addSql("COMMENT ON COLUMN webhook_deliveries.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN webhook_deliveries.suscripcion_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN webhook_deliveries.ultimo_intento IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN webhook_deliveries.creado_en IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_wh_delivery_suscripcion ON webhook_deliveries (suscripcion_id, creado_en)');
        $this->addSql('CREATE INDEX idx_wh_delivery_estado ON webhook_deliveries (estado)');

        // ── tarifas ───────────────────────────────────────────────────────────
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

        // ── configuracion_clinica ─────────────────────────────────────────────
        $this->addSql("CREATE TABLE configuracion_clinica (
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
        )");

        // ── Foreign Keys ──────────────────────────────────────────────────────
        $this->addSql('ALTER TABLE bitacora_operativa ADD CONSTRAINT FK_5BF9F65D7310DAD4 FOREIGN KEY (paciente_id) REFERENCES pacientes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bitacora_operativa ADD CONSTRAINT FK_5BF9F65DFE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE condicion_domicilio ADD CONSTRAINT FK_FCA7566F7310DAD4 FOREIGN KEY (paciente_id) REFERENCES pacientes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE disponibilidad_trabajador ADD CONSTRAINT FK_D838D311EC3656E FOREIGN KEY (trabajador_id) REFERENCES trabajadores (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE documentos_trabajador ADD CONSTRAINT FK_C844647EEC3656E FOREIGN KEY (trabajador_id) REFERENCES trabajadores (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE documentos_trabajador ADD CONSTRAINT FK_C844647E446C2E6 FOREIGN KEY (subido_por_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE eventos_adversos ADD CONSTRAINT FK_70627DBB7310DAD4 FOREIGN KEY (paciente_id) REFERENCES pacientes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE eventos_adversos ADD CONSTRAINT FK_70627DBBEC3656E FOREIGN KEY (trabajador_id) REFERENCES trabajadores (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE eventos_adversos ADD CONSTRAINT FK_70627DBBFE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE eventos_adversos ADD CONSTRAINT fk_evento_responsable FOREIGN KEY (responsable_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE eventos_adversos ADD CONSTRAINT fk_evento_revisado_por FOREIGN KEY (revisado_por_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE eventos_adversos ADD CONSTRAINT fk_evento_cerrado_por FOREIGN KEY (cerrado_por_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facturas ADD CONSTRAINT FK_622B9C0F8877858D FOREIGN KEY (mandante_id) REFERENCES mandantes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facturas ADD CONSTRAINT FK_622B9C0FFE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE historial_comunicacion ADD CONSTRAINT FK_982C73027310DAD4 FOREIGN KEY (paciente_id) REFERENCES pacientes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE historial_comunicacion ADD CONSTRAINT FK_982C7302FE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE items_liquidacion ADD CONSTRAINT FK_A813E608326DAF0C FOREIGN KEY (liquidacion_id) REFERENCES liquidaciones_mensuales (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE items_liquidacion ADD CONSTRAINT FK_A813E60869C5211E FOREIGN KEY (turno_id) REFERENCES turnos (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE liquidaciones_mensuales ADD CONSTRAINT FK_B32C50FEEC3656E FOREIGN KEY (trabajador_id) REFERENCES trabajadores (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE liquidaciones_mensuales ADD CONSTRAINT FK_B32C50FEFE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notificaciones ADD CONSTRAINT fk_notif_destinatario FOREIGN KEY (destinatario_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE pacientes ADD CONSTRAINT FK_971B78518877858D FOREIGN KEY (mandante_id) REFERENCES mandantes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT fk_reset_password_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE seguimientos_evento ADD CONSTRAINT FK_BDC9177F87A5F842 FOREIGN KEY (evento_id) REFERENCES eventos_adversos (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE seguimientos_evento ADD CONSTRAINT FK_BDC9177FFE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tarifas ADD CONSTRAINT FK_7A6F316D8877858D FOREIGN KEY (mandante_id) REFERENCES mandantes (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE trabajadores ADD CONSTRAINT FK_CF4C0B49A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE turnos ADD CONSTRAINT FK_B85558187310DAD4 FOREIGN KEY (paciente_id) REFERENCES pacientes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE turnos ADD CONSTRAINT FK_B8555818EC3656E FOREIGN KEY (trabajador_id) REFERENCES trabajadores (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE turnos ADD CONSTRAINT FK_B85558188E90C9F2 FOREIGN KEY (turno_original_id) REFERENCES turnos (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE turnos ADD CONSTRAINT fk_turnos_trabajador_original FOREIGN KEY (trabajador_original_id) REFERENCES trabajadores (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE turnos ADD CONSTRAINT FK_B8555818FE35D8C4 FOREIGN KEY (creado_por_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE webhook_deliveries ADD CONSTRAINT fk_wh_delivery_suscripcion FOREIGN KEY (suscripcion_id) REFERENCES webhook_suscripciones (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bitacora_operativa DROP CONSTRAINT FK_5BF9F65D7310DAD4');
        $this->addSql('ALTER TABLE bitacora_operativa DROP CONSTRAINT FK_5BF9F65DFE35D8C4');
        $this->addSql('ALTER TABLE condicion_domicilio DROP CONSTRAINT FK_FCA7566F7310DAD4');
        $this->addSql('ALTER TABLE disponibilidad_trabajador DROP CONSTRAINT FK_D838D311EC3656E');
        $this->addSql('ALTER TABLE documentos_trabajador DROP CONSTRAINT FK_C844647EEC3656E');
        $this->addSql('ALTER TABLE documentos_trabajador DROP CONSTRAINT FK_C844647E446C2E6');
        $this->addSql('ALTER TABLE eventos_adversos DROP CONSTRAINT FK_70627DBB7310DAD4');
        $this->addSql('ALTER TABLE eventos_adversos DROP CONSTRAINT FK_70627DBBEC3656E');
        $this->addSql('ALTER TABLE eventos_adversos DROP CONSTRAINT FK_70627DBBFE35D8C4');
        $this->addSql('ALTER TABLE eventos_adversos DROP CONSTRAINT fk_evento_responsable');
        $this->addSql('ALTER TABLE eventos_adversos DROP CONSTRAINT fk_evento_revisado_por');
        $this->addSql('ALTER TABLE eventos_adversos DROP CONSTRAINT fk_evento_cerrado_por');
        $this->addSql('ALTER TABLE facturas DROP CONSTRAINT FK_622B9C0F8877858D');
        $this->addSql('ALTER TABLE facturas DROP CONSTRAINT FK_622B9C0FFE35D8C4');
        $this->addSql('ALTER TABLE historial_comunicacion DROP CONSTRAINT FK_982C73027310DAD4');
        $this->addSql('ALTER TABLE historial_comunicacion DROP CONSTRAINT FK_982C7302FE35D8C4');
        $this->addSql('ALTER TABLE items_liquidacion DROP CONSTRAINT FK_A813E608326DAF0C');
        $this->addSql('ALTER TABLE items_liquidacion DROP CONSTRAINT FK_A813E60869C5211E');
        $this->addSql('ALTER TABLE liquidaciones_mensuales DROP CONSTRAINT FK_B32C50FEEC3656E');
        $this->addSql('ALTER TABLE liquidaciones_mensuales DROP CONSTRAINT FK_B32C50FEFE35D8C4');
        $this->addSql('ALTER TABLE notificaciones DROP CONSTRAINT fk_notif_destinatario');
        $this->addSql('ALTER TABLE pacientes DROP CONSTRAINT FK_971B78518877858D');
        $this->addSql('ALTER TABLE reset_password_request DROP CONSTRAINT fk_reset_password_user');
        $this->addSql('ALTER TABLE seguimientos_evento DROP CONSTRAINT FK_BDC9177F87A5F842');
        $this->addSql('ALTER TABLE seguimientos_evento DROP CONSTRAINT FK_BDC9177FFE35D8C4');
        $this->addSql('ALTER TABLE tarifas DROP CONSTRAINT FK_7A6F316D8877858D');
        $this->addSql('ALTER TABLE trabajadores DROP CONSTRAINT FK_CF4C0B49A76ED395');
        $this->addSql('ALTER TABLE turnos DROP CONSTRAINT FK_B85558187310DAD4');
        $this->addSql('ALTER TABLE turnos DROP CONSTRAINT FK_B8555818EC3656E');
        $this->addSql('ALTER TABLE turnos DROP CONSTRAINT FK_B85558188E90C9F2');
        $this->addSql('ALTER TABLE turnos DROP CONSTRAINT fk_turnos_trabajador_original');
        $this->addSql('ALTER TABLE turnos DROP CONSTRAINT FK_B8555818FE35D8C4');
        $this->addSql('ALTER TABLE webhook_deliveries DROP CONSTRAINT fk_wh_delivery_suscripcion');

        $this->addSql('DROP TABLE seguimientos_evento');
        $this->addSql('DROP TABLE eventos_adversos');
        $this->addSql('DROP TABLE historial_comunicacion');
        $this->addSql('DROP TABLE bitacora_operativa');
        $this->addSql('DROP TABLE condicion_domicilio');
        $this->addSql('DROP TABLE disponibilidad_trabajador');
        $this->addSql('DROP TABLE documentos_trabajador');
        $this->addSql('DROP TABLE items_liquidacion');
        $this->addSql('DROP TABLE facturas');
        $this->addSql('DROP TABLE liquidaciones_mensuales');
        $this->addSql('DROP TABLE notificaciones');
        $this->addSql('DROP TABLE webhook_deliveries');
        $this->addSql('DROP TABLE webhook_suscripciones');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql('DROP TABLE tarifas');
        $this->addSql('DROP TABLE configuracion_clinica');
        $this->addSql('DROP TABLE turnos');
        $this->addSql('DROP TABLE pacientes');
        $this->addSql('DROP TABLE trabajadores');
        $this->addSql('DROP TABLE mandantes');
        $this->addSql('DROP TABLE ext_log_entries');
        $this->addSql('DROP TABLE users');
    }
}
