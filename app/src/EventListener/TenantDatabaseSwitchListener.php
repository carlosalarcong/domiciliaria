<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\TenantContext;
use App\Service\TenantResolver;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Listener con priority 1000: resuelve el tenant desde el subdominio y
 * dispara SwitchDbEvent para que el hakam bundle cambie la conexión tenant.
 */
class TenantDatabaseSwitchListener implements EventSubscriberInterface
{
    private ?string $lastSwitchedSlug = null;

    public function __construct(
        private readonly TenantResolver          $tenantResolver,
        private readonly TenantContext           $tenantContext,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface          $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request   = $event->getRequest();
        $subdomain = $this->tenantResolver->extractSubdomain($request->getHost());

        if ($subdomain === null) {
            // Dominio principal — no se cambia BD
            return;
        }

        // Evitar re-switch innecesario en el mismo request
        if ($this->lastSwitchedSlug === $subdomain) {
            return;
        }

        $tenant = $this->tenantResolver->getTenantBySlug($subdomain);

        if ($tenant === null) {
            $this->logger->warning('Tenant no encontrado para subdominio: {subdomain}', [
                'subdomain' => $subdomain,
            ]);
            return;
        }

        $this->tenantContext->setCurrentTenant($tenant);
        $this->lastSwitchedSlug = $subdomain;

        $this->logger->info('Tenant resuelto: {name} → BD: {db}', [
            'name' => $tenant['name'] ?? $subdomain,
            'db'   => $tenant['database_name'],
        ]);

        // Dispara el evento que el hakam DbSwitchEventListener escucha
        $this->eventDispatcher->dispatch(new SwitchDbEvent((string) $tenant['id']));
    }
}
