<?php

declare(strict_types=1);

namespace App\Message;

final class TurnoParcialVencidoMessage
{
    public function __construct(
        public readonly string $turnoId,
        public readonly string $pacienteNombre,
        public readonly string $trabajadorNombre,
        public readonly string $fecha,
        public readonly string $tipoTurno,
    ) {}
}
