<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330000007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega fecha_vencimiento a documentos_trabajador';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documentos_trabajador ADD COLUMN fecha_vencimiento DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE documentos_trabajador DROP COLUMN fecha_vencimiento');
    }
}
