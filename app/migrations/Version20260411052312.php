<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260411052312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega trabajador_original_id en turnos para conservar el trabajador original al asignar reemplazos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE turnos ADD trabajador_original_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE turnos ADD CONSTRAINT FK_B8555818E6CBDA59 FOREIGN KEY (trabajador_original_id) REFERENCES trabajadores (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_B8555818E6CBDA59 ON turnos (trabajador_original_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_B8555818E6CBDA59');
        $this->addSql('ALTER TABLE turnos DROP CONSTRAINT FK_B8555818E6CBDA59');
        $this->addSql('ALTER TABLE turnos DROP trabajador_original_id');
    }
}
