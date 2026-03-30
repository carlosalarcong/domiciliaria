<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega campo responsable_id a eventos_adversos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE eventos_adversos ADD COLUMN responsable_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE eventos_adversos ADD CONSTRAINT fk_evento_responsable FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_evento_responsable ON eventos_adversos (responsable_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE eventos_adversos DROP CONSTRAINT fk_evento_responsable');
        $this->addSql('DROP INDEX idx_evento_responsable');
        $this->addSql('ALTER TABLE eventos_adversos DROP COLUMN responsable_id');
    }
}
