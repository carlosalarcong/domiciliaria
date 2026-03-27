<?php

declare(strict_types=1);

namespace App\Enum;

enum TipoComunicacion: string
{
    case MANDANTE = 'MANDANTE';
    case FAMILIA  = 'FAMILIA';
    case EQUIPO   = 'EQUIPO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MANDANTE => 'Mandante',
            self::FAMILIA  => 'Familia',
            self::EQUIPO   => 'Equipo interno',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::MANDANTE => 'bg-primary',
            self::FAMILIA  => 'bg-success',
            self::EQUIPO   => 'bg-secondary',
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
