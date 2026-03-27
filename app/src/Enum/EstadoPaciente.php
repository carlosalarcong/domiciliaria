<?php

declare(strict_types=1);

namespace App\Enum;

enum EstadoPaciente: string
{
    case ACTIVO       = 'ACTIVO';
    case SUSPENDIDO   = 'SUSPENDIDO';
    case DADO_DE_BAJA = 'DADO_DE_BAJA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACTIVO       => 'Activo',
            self::SUSPENDIDO   => 'Suspendido',
            self::DADO_DE_BAJA => 'Dado de baja',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVO       => 'bg-success',
            self::SUSPENDIDO   => 'bg-warning text-dark',
            self::DADO_DE_BAJA => 'bg-danger',
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
