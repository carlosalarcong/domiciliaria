<?php

declare(strict_types=1);

namespace App\Enum;

enum WebhookEvento: string
{
    // Turnos
    case TURNO_CREADO         = 'turno.creado';
    case TURNO_COMPLETADO     = 'turno.completado';
    case TURNO_DESCUBIERTO    = 'turno.descubierto';

    // Pacientes
    case PACIENTE_CREADO          = 'paciente.creado';
    case PACIENTE_ESTADO_CAMBIADO = 'paciente.estado_cambiado';

    // Liquidaciones
    case LIQUIDACION_GENERADA = 'liquidacion.generada';
    case LIQUIDACION_PAGADA   = 'liquidacion.pagada';

    // Facturas
    case FACTURA_EMITIDA = 'factura.emitida';
    case FACTURA_PAGADA  = 'factura.pagada';

    // Eventos adversos
    case EVENTO_ADVERSO_CREADO = 'evento_adverso.creado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TURNO_CREADO             => 'Turno creado',
            self::TURNO_COMPLETADO         => 'Turno completado',
            self::TURNO_DESCUBIERTO        => 'Turno descubierto',
            self::PACIENTE_CREADO          => 'Paciente creado',
            self::PACIENTE_ESTADO_CAMBIADO => 'Paciente — cambio de estado',
            self::LIQUIDACION_GENERADA     => 'Liquidación generada',
            self::LIQUIDACION_PAGADA       => 'Liquidación pagada',
            self::FACTURA_EMITIDA          => 'Factura emitida',
            self::FACTURA_PAGADA           => 'Factura pagada',
            self::EVENTO_ADVERSO_CREADO    => 'Evento adverso registrado',
        };
    }

    public function grupo(): string
    {
        return match ($this) {
            self::TURNO_CREADO,
            self::TURNO_COMPLETADO,
            self::TURNO_DESCUBIERTO        => 'Turnos',
            self::PACIENTE_CREADO,
            self::PACIENTE_ESTADO_CAMBIADO => 'Pacientes',
            self::LIQUIDACION_GENERADA,
            self::LIQUIDACION_PAGADA       => 'Liquidaciones',
            self::FACTURA_EMITIDA,
            self::FACTURA_PAGADA           => 'Facturas',
            self::EVENTO_ADVERSO_CREADO    => 'Eventos adversos',
        };
    }
}
