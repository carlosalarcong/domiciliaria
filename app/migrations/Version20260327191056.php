<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260327191056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE documentos_trabajador (id UUID NOT NULL, tipo VARCHAR(255) NOT NULL, nombre_original VARCHAR(255) NOT NULL, ruta_archivo VARCHAR(255) NOT NULL, extension VARCHAR(20) NOT NULL, tamano_bytes INT NOT NULL, descripcion VARCHAR(500) DEFAULT NULL, creado_en TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, trabajador_id UUID NOT NULL, subido_por_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C844647EEC3656E ON documentos_trabajador (trabajador_id)');
        $this->addSql('CREATE INDEX IDX_C844647E446C2E6 ON documentos_trabajador (subido_por_id)');
        $this->addSql('ALTER TABLE documentos_trabajador ADD CONSTRAINT FK_C844647EEC3656E FOREIGN KEY (trabajador_id) REFERENCES trabajadores (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE documentos_trabajador ADD CONSTRAINT FK_C844647E446C2E6 FOREIGN KEY (subido_por_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documentos_trabajador DROP CONSTRAINT FK_C844647EEC3656E');
        $this->addSql('ALTER TABLE documentos_trabajador DROP CONSTRAINT FK_C844647E446C2E6');
        $this->addSql('DROP TABLE documentos_trabajador');
    }
}
