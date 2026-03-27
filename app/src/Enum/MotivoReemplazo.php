<?php

declare(strict_types=1);

namespace App\Enum;

enum MotivoReemplazo: string
{
    case LICENCIA    = 'LICENCIA';
    case VACACIONES  = 'VACACIONES';
    case INASISTENCIA = 'INASISTENCIA';
    case OTRO        = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LICENCIA    => 'Licencia médica',
            self::VACACIONES  => 'Vacaciones',
            self::INASISTENCIA => 'Inasistencia',
            self::OTRO        => 'Otro',
        };
    }
}
