<?php

declare(strict_types=1);

namespace App\Enum;

enum TipoDocumento: string
{
    case CONTRATO       = 'CONTRATO';
    case CV             = 'CV';
    case CERTIFICADO    = 'CERTIFICADO';
    case TITULO         = 'TITULO';
    case LICENCIA       = 'LICENCIA';
    case OTRO           = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CONTRATO    => 'Contrato',
            self::CV          => 'Currículum',
            self::CERTIFICADO => 'Certificado',
            self::TITULO      => 'Título profesional',
            self::LICENCIA    => 'Licencia médica',
            self::OTRO        => 'Otro',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CONTRATO    => 'bg-primary',
            self::CV          => 'bg-info text-dark',
            self::CERTIFICADO => 'bg-success',
            self::TITULO      => 'bg-warning text-dark',
            self::LICENCIA    => 'bg-danger',
            self::OTRO        => 'bg-secondary',
        };
    }
}
