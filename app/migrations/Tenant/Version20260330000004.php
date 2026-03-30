<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea tabla notificaciones para notificaciones in-app';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notificaciones (
            id UUID NOT NULL,
            destinatario_id UUID NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            cuerpo TEXT DEFAULT NULL,
            url VARCHAR(500) DEFAULT NULL,
            leida_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN notificaciones.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notificaciones.destinatario_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notificaciones.leida_en IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notificaciones.creado_en IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_notif_dest_leida ON notificaciones (destinatario_id, leida_en)');
        $this->addSql('ALTER TABLE notificaciones ADD CONSTRAINT fk_notif_destinatario FOREIGN KEY (destinatario_id) REFERENCES users(id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notificaciones DROP CONSTRAINT fk_notif_destinatario');
        $this->addSql('DROP TABLE notificaciones');
    }
}
