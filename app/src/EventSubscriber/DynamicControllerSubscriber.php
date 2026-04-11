<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\DynamicControllerResolver;
use App\Service\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Priority 15: resuelve el controller especifico del tenant antes de que Symfony lo ejecute.
 * Si el tenant tiene un override en App\Controller\{Slug}\, lo usa en lugar del Default.
 */
class DynamicControllerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DynamicControllerResolver $controllerResolver,
        private readonly LoggerInterface $logger,
        private readonly array $excludedControllers = [],
        private readonly array $excludedNamespaces = [],
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 15],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Inyectar datos del tenant en los atributos del request
        $tenant = $this->tenantContext->getCurrentTenant();
        if ($tenant !== null) {
            $request->attributes->set('_tenant', $tenant);
            $request->attributes->set('_tenant_db', $tenant['database_name'] ?? null);
        }

        // Obtener el slug del tenant activo (getCurrentSubdomain() devuelve el slug)
        $tenantSlug = $this->tenantContext->getCurrentSubdomain();
        if ($tenantSlug === null || $tenantSlug === '') {
            return;
        }

        // Obtener el controller actual del request
        $originalController = $request->attributes->get('_controller');
        if (!is_string($originalController) || $originalController === '') {
            return;
        }

        // Verificar listas de exclusion
        foreach ($this->excludedNamespaces as $ns) {
            if (str_starts_with($originalController, $ns)) {
                return;
            }
        }
        foreach ($this->excludedControllers as $excluded) {
            if ($originalController === $excluded) {
                return;
            }
        }

        // Resolver el controller segun el tenant
        $resolvedController = $this->controllerResolver->resolveControllerFromRoute(
            $originalController,
            $tenantSlug,
        );

        if ($resolvedController !== $originalController) {
            $this->logger->info('[DynamicControllerSubscriber] Usando override de controller', [
                'tenant' => $tenantSlug,
                'original' => $originalController,
                'override' => $resolvedController,
            ]);
            $request->attributes->set('_controller', $resolvedController);
        }
    }
}
