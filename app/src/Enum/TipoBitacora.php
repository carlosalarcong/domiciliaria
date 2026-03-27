<?php

declare(strict_types=1);

namespace App\Enum;

enum TipoBitacora: string
{
    case NOVEDAD      = 'NOVEDAD';
    case INCIDENCIA   = 'INCIDENCIA';
    case COMUNICACION = 'COMUNICACION';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NOVEDAD      => 'Novedad',
            self::INCIDENCIA   => 'Incidencia',
            self::COMUNICACION => 'Comunicación',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NOVEDAD      => 'bg-info',
            self::INCIDENCIA   => 'bg-danger',
            self::COMUNICACION => 'bg-primary',
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
