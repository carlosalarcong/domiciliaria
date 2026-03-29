<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RateLimitListener
{
    // Paths de exportación que tienen límite restrictivo
    private const EXPORT_PATHS = [
        '/pacientes/exportar',
        '/trabajadores/exportar',
        '/turnos/exportar',
        '/finanzas/exportar',
    ];

    public function __construct(
        private readonly RateLimiterFactory    $loginLimiter,
        private readonly RateLimiterFactory    $apiLimiter,
        private readonly RateLimiterFactory    $exportLimiter,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 200)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();
        $ip      = $request->getClientIp() ?? '0.0.0.0';

        // Rate limit en /login
        if (str_starts_with($path, '/login') && $request->getMethod() === 'POST') {
            $limiter = $this->loginLimiter->create($ip);
            $limit   = $limiter->consume();
            if (!$limit->isAccepted()) {
                $response = new RedirectResponse('/login?rate_limited=1');
                $response->headers->set('X-RateLimit-Remaining', '0');
                $event->setResponse($response);
                return;
            }
        }

        // Rate limit en /api/*
        if (str_starts_with($path, '/api/')) {
            $token  = $this->tokenStorage->getToken();
            $userId = $token?->getUser()?->getUserIdentifier() ?? $ip;
            $limiter = $this->apiLimiter->create('api_' . md5($userId));
            $limit   = $limiter->consume();
            if (!$limit->isAccepted()) {
                $event->setResponse(new JsonResponse(
                    ['error' => 'Rate limit excedido. Máximo 100 requests/minuto.'],
                    Response::HTTP_TOO_MANY_REQUESTS
                ));
                return;
            }
        }

        // Rate limit en exportaciones
        foreach (self::EXPORT_PATHS as $exportPath) {
            if (str_starts_with($path, $exportPath)) {
                $token  = $this->tokenStorage->getToken();
                $userId = $token?->getUser()?->getUserIdentifier() ?? $ip;
                $limiter = $this->exportLimiter->create('export_' . md5($userId));
                $limit   = $limiter->consume();
                if (!$limit->isAccepted()) {
                    $event->setResponse(new JsonResponse(
                        ['error' => 'Límite de exportaciones excedido. Máximo 5 por hora.'],
                        Response::HTTP_TOO_MANY_REQUESTS
                    ));
                }
                return;
            }
        }
    }
}
