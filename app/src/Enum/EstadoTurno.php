<?php

declare(strict_types=1);

namespace App\Enum;

enum EstadoTurno: string
{
    case CUBIERTO     = 'CUBIERTO';
    case PARCIAL      = 'PARCIAL';
    case DESCUBIERTO  = 'DESCUBIERTO';
    case COMPLETADO   = 'COMPLETADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CUBIERTO    => 'Cubierto',
            self::PARCIAL     => 'Parcial',
            self::DESCUBIERTO => 'Descubierto',
            self::COMPLETADO  => 'Completado',
        };
    }

    public function colorCalendario(): string
    {
        return match ($this) {
            self::CUBIERTO    => '#198754',
            self::PARCIAL     => '#ffc107',
            self::DESCUBIERTO => '#dc3545',
            self::COMPLETADO  => '#0d6efd',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CUBIERTO    => 'bg-success',
            self::PARCIAL     => 'bg-warning text-dark',
            self::DESCUBIERTO => 'bg-danger',
            self::COMPLETADO  => 'bg-primary',
        };
    }
}
