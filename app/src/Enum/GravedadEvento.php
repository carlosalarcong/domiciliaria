<?php

declare(strict_types=1);

namespace App\Enum;

enum GravedadEvento: string
{
    case LEVE     = 'LEVE';
    case MODERADO = 'MODERADO';
    case GRAVE    = 'GRAVE';
    case CRITICO  = 'CRITICO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LEVE     => 'Leve',
            self::MODERADO => 'Moderado',
            self::GRAVE    => 'Grave',
            self::CRITICO  => 'Crítico',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::LEVE     => 'bg-success',
            self::MODERADO => 'bg-warning text-dark',
            self::GRAVE    => 'bg-danger',
            self::CRITICO  => 'bg-dark',
        };
    }

    public function requiereNotificacion(): bool
    {
        return match ($this) {
            self::LEVE, self::MODERADO => false,
            self::GRAVE, self::CRITICO => true,
        };
    }
}
