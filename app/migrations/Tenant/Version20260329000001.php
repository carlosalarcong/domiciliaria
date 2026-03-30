<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seguridad: campos cifrados en pacientes/trabajadores, 2FA en users, audit_logs';
    }

    public function up(Schema $schema): void
    {
        // ── Pacientes: encriptación + nuevos campos clínicos ───────────────────
        $this->addSql('ALTER TABLE pacientes ALTER COLUMN rut TYPE TEXT');
        $this->addSql('ALTER TABLE pacientes DROP CONSTRAINT IF EXISTS uniq_rut_pacientes');
        $this->addSql('ALTER TABLE pacientes ALTER COLUMN rut DROP NOT NULL');
        $this->addSql('ALTER TABLE pacientes ADD COLUMN IF NOT EXISTS rut_hash VARCHAR(64)');
        $this->addSql('ALTER TABLE pacientes ADD COLUMN IF NOT EXISTS diagnosticos TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE pacientes ADD COLUMN IF NOT EXISTS medicamentos TEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_pacientes_rut_hash ON pacientes (rut_hash) WHERE rut_hash IS NOT NULL');

        // ── Trabajadores: encriptación + datos bancarios/previsionales ─────────
        $this->addSql('ALTER TABLE trabajadores ALTER COLUMN rut TYPE TEXT');
        $this->addSql('ALTER TABLE trabajadores DROP CONSTRAINT IF EXISTS uniq_rut_trabajadores');
        $this->addSql('ALTER TABLE trabajadores ALTER COLUMN rut DROP NOT NULL');
        $this->addSql('ALTER TABLE trabajadores ADD COLUMN IF NOT EXISTS rut_hash VARCHAR(64)');
        $this->addSql('ALTER TABLE trabajadores ADD COLUMN IF NOT EXISTS cuenta_bancaria TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE trabajadores ADD COLUMN IF NOT EXISTS datos_previsionales TEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_trabajadores_rut_hash ON trabajadores (rut_hash) WHERE rut_hash IS NOT NULL');

        // ── Users: campos 2FA ──────────────────────────────────────────────────
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS backup_codes JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE');

        // ── Audit logs ─────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS audit_logs (
                id           SERIAL PRIMARY KEY,
                evento       VARCHAR(50)  NOT NULL,
                email_intentado VARCHAR(255) DEFAULT NULL,
                ip_address   VARCHAR(45)  NOT NULL,
                user_agent   VARCHAR(500) DEFAULT NULL,
                tenant       VARCHAR(100) DEFAULT NULL,
                detalles     JSON         NOT NULL DEFAULT '{}',
                created_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_audit_ip      ON audit_logs (ip_address)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pacientes ALTER COLUMN rut TYPE VARCHAR(15)');
        $this->addSql('DROP INDEX IF EXISTS uniq_pacientes_rut_hash');
        $this->addSql('ALTER TABLE pacientes DROP COLUMN IF EXISTS rut_hash');
        $this->addSql('ALTER TABLE pacientes DROP COLUMN IF EXISTS diagnosticos');
        $this->addSql('ALTER TABLE pacientes DROP COLUMN IF EXISTS medicamentos');

        $this->addSql('ALTER TABLE trabajadores ALTER COLUMN rut TYPE VARCHAR(15)');
        $this->addSql('DROP INDEX IF EXISTS uniq_trabajadores_rut_hash');
        $this->addSql('ALTER TABLE trabajadores DROP COLUMN IF EXISTS rut_hash');
        $this->addSql('ALTER TABLE trabajadores DROP COLUMN IF EXISTS cuenta_bancaria');
        $this->addSql('ALTER TABLE trabajadores DROP COLUMN IF EXISTS datos_previsionales');

        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS totp_secret');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS backup_codes');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS two_factor_enabled');

        $this->addSql('DROP TABLE IF EXISTS audit_logs');
    }
}
