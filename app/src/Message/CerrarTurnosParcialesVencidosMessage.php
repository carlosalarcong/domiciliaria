<?php

declare(strict_types=1);

namespace App\Message;

final class CerrarTurnosParcialesVencidosMessage
{
    public function __construct(
        public readonly int $horas = 26,
        public readonly bool $allTenants = true,
    ) {}
}
