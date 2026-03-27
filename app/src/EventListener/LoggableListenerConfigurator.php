<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Log\LogEntry;
use Gedmo\Loggable\LoggableListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Configura el LoggableListener de Gedmo para usar nuestra entidad
 * LogEntry personalizada (con tipo json en lugar del array obsoleto).
 */
class LoggableListenerConfigurator
{
    public function __construct(
        private readonly LoggableListener $loggableListener,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $this->loggableListener->setDefaultLogEntryClass(LogEntry::class);
    }
}
