<?php

declare(strict_types=1);

namespace App\Enum;

enum TipoServicio: string
{
    case TURNO_12H = 'TURNO_12H';
    case TURNO_24H = 'TURNO_24H';
    case VISITA    = 'VISITA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TURNO_12H => 'Turno 12 horas',
            self::TURNO_24H => 'Turno 24 horas',
            self::VISITA    => 'Visita',
        };
    }

    public static function choices(): array
    {
        return array_combine(
            array_map(fn($e) => $e->etiqueta(), self::cases()),
            array_map(fn($e) => $e->value, self::cases()),
        );
    }
}
