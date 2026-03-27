<?php

declare(strict_types=1);

namespace App\Enum;

enum EstadoEvento: string
{
    case ABIERTO    = 'ABIERTO';
    case EN_PROCESO = 'EN_PROCESO';
    case CERRADO    = 'CERRADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ABIERTO    => 'Abierto',
            self::EN_PROCESO => 'En proceso',
            self::CERRADO    => 'Cerrado',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ABIERTO    => 'bg-danger',
            self::EN_PROCESO => 'bg-warning text-dark',
            self::CERRADO    => 'bg-success',
        };
    }
}
