<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flujo formal de eventos adversos: revisadoPor, revisadoEn, cerradoPor';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE eventos_adversos ADD COLUMN revisado_por_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE eventos_adversos ADD COLUMN revisado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE eventos_adversos ADD COLUMN cerrado_por_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN eventos_adversos.revisado_en IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE eventos_adversos ADD CONSTRAINT fk_evento_revisado_por FOREIGN KEY (revisado_por_id) REFERENCES users(id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE eventos_adversos ADD CONSTRAINT fk_evento_cerrado_por FOREIGN KEY (cerrado_por_id) REFERENCES users(id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE eventos_adversos DROP CONSTRAINT fk_evento_revisado_por');
        $this->addSql('ALTER TABLE eventos_adversos DROP CONSTRAINT fk_evento_cerrado_por');
        $this->addSql('ALTER TABLE eventos_adversos DROP COLUMN revisado_por_id');
        $this->addSql('ALTER TABLE eventos_adversos DROP COLUMN revisado_en');
        $this->addSql('ALTER TABLE eventos_adversos DROP COLUMN cerrado_por_id');
    }
}
