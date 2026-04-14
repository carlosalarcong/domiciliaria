<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\Uuid;

final readonly class EjecutarSincronizacionMessage
{
    public function __construct(
        private Uuid $sincronizacionId,
    ) {}

    public function getSincronizacionId(): Uuid
    {
        return $this->sincronizacionId;
    }
}
