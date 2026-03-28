<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\TenantContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener con priority 10: bloquea acceso a tenants SUSPENDIDOS.
 * Debe ejecutarse DESPUÉS de TenantDatabaseSwitchListener (priority 1000).
 */
class AuthenticationListener implements EventSubscriberInterface
{
    /** Rutas que siempre pasan sin verificación de tenant */
    private const PUBLIC_PATHS = [
        '/_wdt/',
        '/_profiler/',
        '/favicon',
        '/_error/',
    ];

    public function __construct(private readonly TenantContext $tenantContext)
    {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        foreach (self::PUBLIC_PATHS as $public) {
            if (str_starts_with($path, $public)) {
                return;
            }
        }

        $tenant = $this->tenantContext->getCurrentTenant();

        if ($tenant === null) {
            return; // Dominio principal, sin restricción de tenant
        }

        $status = $tenant['database_status'] ?? 'DATABASE_MIGRATED';

        // Si el tenant está suspendido (base no migrada o desactivado), bloquear
        if ($status === 'DATABASE_NOT_CREATED') {
            $event->setResponse(new JsonResponse([
                'error'  => 'tenant_suspended',
                'detail' => 'Esta clínica está temporalmente suspendida.',
            ], 403));
        }
    }
}
