<?php

declare(strict_types=1);

namespace App\Enum;

enum TipoTurno: string
{
    case T12H_DIA   = 'T12H_DIA';
    case T12H_NOCHE = 'T12H_NOCHE';
    case T24H       = 'T24H';
    case VISITA     = 'VISITA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::T12H_DIA   => 'Turno 12h Día',
            self::T12H_NOCHE => 'Turno 12h Noche',
            self::T24H       => 'Turno 24h',
            self::VISITA     => 'Visita',
        };
    }

    public function duracionHoras(): int
    {
        return match ($this) {
            self::T12H_DIA, self::T12H_NOCHE => 12,
            self::T24H                       => 24,
            self::VISITA                     => 2,
        };
    }
}
