<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260410203233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega motivo_anulacion en liquidaciones_mensuales.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE liquidaciones_mensuales ADD motivo_anulacion TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE liquidaciones_mensuales DROP motivo_anulacion');
    }
}
