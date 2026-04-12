<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\TenantContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Establece el path de traducciones específico del tenant en los atributos del request.
 *
 * Priority 25: ejecuta ANTES del LocaleListener de Symfony (priority 20),
 * de modo que cualquier servicio que lea _tenant_translation_path ya lo
 * encuentre disponible al momento de resolver mensajes.
 *
 * Convención de directorios:
 *   translations/{slug}/messages.es.yaml   ← override del tenant
 *   translations/messages.es.yaml          ← fallback global
 */
class TenantTranslationListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly string $projectDir,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 25],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $slug = $this->tenantContext->getCurrentSubdomain() ?? 'default';

        $request->attributes->set('_tenant_slug', $slug);
        $request->attributes->set('_tenant_translation_path', $this->resolvePath($slug));
    }

    private function resolvePath(string $slug): string
    {
        $tenantPath = $this->projectDir . '/translations/' . $slug;

        return is_dir($tenantPath)
            ? $tenantPath
            : $this->projectDir . '/translations';
    }
}
