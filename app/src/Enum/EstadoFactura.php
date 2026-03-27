<?php

declare(strict_types=1);

namespace App\Enum;

enum EstadoFactura: string
{
    case BORRADOR  = 'BORRADOR';
    case EMITIDA   = 'EMITIDA';
    case PAGADA    = 'PAGADA';
    case VENCIDA   = 'VENCIDA';
    case ANULADA   = 'ANULADA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::EMITIDA  => 'Emitida',
            self::PAGADA   => 'Pagada',
            self::VENCIDA  => 'Vencida',
            self::ANULADA  => 'Anulada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::BORRADOR => 'bg-secondary',
            self::EMITIDA  => 'bg-primary',
            self::PAGADA   => 'bg-success',
            self::VENCIDA  => 'bg-danger',
            self::ANULADA  => 'bg-dark',
        };
    }
}
