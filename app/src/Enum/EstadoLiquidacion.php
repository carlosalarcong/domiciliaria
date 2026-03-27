<?php

declare(strict_types=1);

namespace App\Enum;

enum EstadoLiquidacion: string
{
    case BORRADOR  = 'BORRADOR';
    case APROBADA  = 'APROBADA';
    case PAGADA    = 'PAGADA';
    case ANULADA   = 'ANULADA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::APROBADA => 'Aprobada',
            self::PAGADA   => 'Pagada',
            self::ANULADA  => 'Anulada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::BORRADOR => 'bg-secondary',
            self::APROBADA => 'bg-primary',
            self::PAGADA   => 'bg-success',
            self::ANULADA  => 'bg-danger',
        };
    }
}
