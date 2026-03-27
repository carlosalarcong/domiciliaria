<?php

declare(strict_types=1);

namespace App\Enum;

enum EstadoTrabajador: string
{
    case ACTIVO     = 'ACTIVO';
    case INACTIVO   = 'INACTIVO';
    case SUSPENDIDO = 'SUSPENDIDO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACTIVO     => 'Activo',
            self::INACTIVO   => 'Inactivo',
            self::SUSPENDIDO => 'Suspendido',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVO     => 'bg-success',
            self::INACTIVO   => 'bg-secondary',
            self::SUSPENDIDO => 'bg-warning text-dark',
        };
    }
}
