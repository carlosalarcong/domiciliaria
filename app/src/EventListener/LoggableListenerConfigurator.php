<?php

declare(strict_types=1);

namespace App\EventListener;

use Gedmo\Loggable\LoggableListener;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Registra el usuario autenticado en el LoggableListener de Gedmo
 * justo antes de cada controller, cuando la sesión de seguridad ya está cargada.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 0)]
class LoggableListenerConfigurator
{
    public function __construct(
        private readonly LoggableListener $loggableListener,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token !== null && $token->getUser() instanceof UserInterface) {
            $this->loggableListener->setUsername($token->getUser()->getUserIdentifier());
        }
    }
}
