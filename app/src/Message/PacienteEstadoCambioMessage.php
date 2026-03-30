<?php

declare(strict_types=1);

namespace App\Message;

final class PacienteEstadoCambioMessage
{
    public function __construct(
        public readonly string $pacienteId,
        public readonly string $pacienteNombre,
        public readonly string $estadoAnterior,
        public readonly string $estadoNuevo,
    ) {}
}
