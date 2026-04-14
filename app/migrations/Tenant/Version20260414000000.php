<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega tabla tenant de sincronizaciones externas programadas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sincronizaciones_externas (
            id UUID NOT NULL,
            nombre VARCHAR(150) NOT NULL,
            url_endpoint VARCHAR(500) NOT NULL,
            metodo VARCHAR(10) NOT NULL DEFAULT \'GET\',
            headers JSON DEFAULT NULL,
            expresion_cron VARCHAR(100) NOT NULL,
            activa BOOLEAN NOT NULL DEFAULT TRUE,
            ultima_ejecucion TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            ultimo_resultado TEXT DEFAULT NULL,
            ultimo_estado VARCHAR(20) DEFAULT NULL,
            creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            creado_por_id UUID DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX IDX_SYNC_EXTERNA_CREADO_POR ON sincronizaciones_externas (creado_por_id)');
        $this->addSql('ALTER TABLE sincronizaciones_externas ADD CONSTRAINT FK_SYNC_EXTERNA_CREADO_POR FOREIGN KEY (creado_por_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sincronizaciones_externas DROP CONSTRAINT FK_SYNC_EXTERNA_CREADO_POR');
        $this->addSql('DROP TABLE sincronizaciones_externas');
    }
}
