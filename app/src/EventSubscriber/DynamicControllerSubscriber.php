<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Priority 15: permite sobrescribir controllers por tenant si se requiere.
 * En la implementación base solo registra el tenant activo en el request.
 */
class DynamicControllerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TenantContext  $tenantContext,
        private readonly LoggerInterface $logger,
        private readonly array $excludedControllers = [],
        private readonly array $excludedNamespaces   = [],
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 15],
        ];
    }

    public function onKernelRequest(\Symfony\Component\HttpKernel\Event\RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $tenant = $this->tenantContext->getCurrentTenant();
        if ($tenant !== null) {
            // Inyectar el tenant en los atributos del request para que los controllers lo lean
            $event->getRequest()->attributes->set('_tenant', $tenant);
            $event->getRequest()->attributes->set('_tenant_db', $tenant['database_name'] ?? null);
        }
    }
}
