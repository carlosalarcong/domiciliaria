<?php

declare(strict_types=1);

namespace App\Enum;

enum TipoEventoAdverso: string
{
    case CAIDA             = 'CAIDA';
    case MEDICACION        = 'MEDICACION';
    case LESION            = 'LESION';
    case INFECCION         = 'INFECCION';
    case DETERIORO_CLINICO = 'DETERIORO_CLINICO';
    case ACCIDENTE_LABORAL = 'ACCIDENTE_LABORAL';
    case CONDUCTA          = 'CONDUCTA';
    case RECLAMO           = 'RECLAMO';
    case OTRO              = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CAIDA             => 'Caída',
            self::MEDICACION        => 'Error de medicación',
            self::LESION            => 'Lesión',
            self::INFECCION         => 'Infección',
            self::DETERIORO_CLINICO => 'Deterioro clínico',
            self::ACCIDENTE_LABORAL => 'Accidente laboral',
            self::CONDUCTA          => 'Problema de conducta',
            self::RECLAMO           => 'Reclamo',
            self::OTRO              => 'Otro',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::CAIDA             => 'bi-person-falling',
            self::MEDICACION        => 'bi-capsule',
            self::LESION            => 'bi-bandaid',
            self::INFECCION         => 'bi-virus',
            self::DETERIORO_CLINICO => 'bi-heart-pulse',
            self::ACCIDENTE_LABORAL => 'bi-cone-striped',
            self::CONDUCTA          => 'bi-exclamation-circle',
            self::RECLAMO           => 'bi-megaphone',
            self::OTRO              => 'bi-question-circle',
        };
    }
}
