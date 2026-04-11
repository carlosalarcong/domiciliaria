<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260411052528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega fue_pagada en liquidaciones_mensuales.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE liquidaciones_mensuales ADD fue_pagada BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE liquidaciones_mensuales DROP fue_pagada');
    }
}
