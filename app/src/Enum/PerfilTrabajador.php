<?php

declare(strict_types=1);

namespace App\Enum;

enum PerfilTrabajador: string
{
    case TENS      = 'TENS';
    case CUIDADOR  = 'CUIDADOR';
    case ENFERMERA = 'ENFERMERA';
    case OTRO      = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TENS      => 'TENS',
            self::CUIDADOR  => 'Cuidador/a',
            self::ENFERMERA => 'Enfermera/o',
            self::OTRO      => 'Otro',
        };
    }
}
