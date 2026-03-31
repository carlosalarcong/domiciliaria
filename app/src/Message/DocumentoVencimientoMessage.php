<?php

declare(strict_types=1);

namespace App\Message;

final readonly class DocumentoVencimientoMessage
{
    public function __construct(
        public string $documentoId,
        public string $trabajadorId,
        public string $trabajadorNombre,
        public string $tipoDocumento,
        public string $descripcion,
        public string $fechaVencimiento,
        public int    $diasRestantes,
    ) {}
}
