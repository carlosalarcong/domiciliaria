<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega ultima_alerta_descubierto_en para evitar alertas duplicadas de turnos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE turnos ADD COLUMN IF NOT EXISTS ultima_alerta_descubierto_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE turnos DROP COLUMN IF EXISTS ultima_alerta_descubierto_en');
    }
}
