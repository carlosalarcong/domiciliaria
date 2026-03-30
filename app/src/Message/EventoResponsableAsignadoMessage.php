<?php

declare(strict_types=1);

namespace App\Message;

final readonly class EventoResponsableAsignadoMessage
{
    public function __construct(
        public string $eventoId,
        public string $pacienteNombre,
        public string $tipo,
        public string $gravedad,
        public string $fecha,
        public string $responsableEmail,
        public string $responsableNombre,
    ) {}
}
