<?php

declare(strict_types=1);

namespace App\Enum;

enum TipoConcepto: string
{
    case TURNO_DIA    = 'TURNO_DIA';
    case TURNO_NOCHE  = 'TURNO_NOCHE';
    case TURNO_24H    = 'TURNO_24H';
    case VISITA       = 'VISITA';
    case REEMPLAZO    = 'REEMPLAZO';
    case BONO         = 'BONO';
    case DESCUENTO    = 'DESCUENTO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TURNO_DIA   => 'Turno 12h día',
            self::TURNO_NOCHE => 'Turno 12h noche',
            self::TURNO_24H   => 'Turno 24h',
            self::VISITA      => 'Visita',
            self::REEMPLAZO   => 'Reemplazo',
            self::BONO        => 'Bono',
            self::DESCUENTO   => 'Descuento',
        };
    }
}
