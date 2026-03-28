<?php

declare(strict_types=1);

namespace App\Enum;

enum EstadoTenant: string
{
    case ACTIVA = 'ACTIVA';
    case SUSPENDIDA = 'SUSPENDIDA';

    public function etiqueta(): string
    {
        return match($this) {
            self::ACTIVA => 'Activa',
            self::SUSPENDIDA => 'Suspendida',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::ACTIVA => 'badge bg-success',
            self::SUSPENDIDA => 'badge bg-danger',
        };
    }
}
