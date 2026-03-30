<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Tenant\Log\LogEntry;
use Gedmo\Loggable\LoggableListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Configura el LoggableListener de Gedmo para usar nuestra entidad
 * LogEntry personalizada y registra el usuario autenticado como autor.
 */
class LoggableListenerConfigurator
{
    public function __construct(
        private readonly LoggableListener $loggableListener,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->loggableListener->setDefaultLogEntryClass(LogEntry::class);

        $token = $this->tokenStorage->getToken();
        if ($token !== null && $token->getUser() instanceof UserInterface) {
            $this->loggableListener->setUsername($token->getUser()->getUserIdentifier());
        }
    }
}
