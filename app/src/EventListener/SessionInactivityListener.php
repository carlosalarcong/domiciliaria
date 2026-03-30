<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Invalida la sesión tras 30 minutos de inactividad.
 */
class SessionInactivityListener
{
    private const INACTIVITY_LIMIT = 1800; // 30 minutos

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RouterInterface       $router,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 50)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $token   = $this->tokenStorage->getToken();

        if ($token === null || !$token->getUser()) {
            return;
        }

        // Rutas públicas que no deben redirigir
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/login') || str_starts_with($path, '/reset-password')) {
            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        $session      = $request->getSession();
        $lastActivity = $session->get('_last_activity', time());

        if ((time() - $lastActivity) > self::INACTIVITY_LIMIT) {
            $this->tokenStorage->setToken(null);
            $session->invalidate();
            $session->getFlashBag()->add('info', 'Tu sesión expiró por inactividad. Por favor inicia sesión nuevamente.');
            $event->setResponse(new RedirectResponse($this->router->generate('app_login')));
            return;
        }

        $session->set('_last_activity', time());
    }
}
