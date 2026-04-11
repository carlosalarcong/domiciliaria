<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Resuelve el controller real a ejecutar segun el tenant activo.
 *
 * Orden de busqueda para un controller App\Controller\Default\FooController::action
 * con tenant slug "demo":
 *   1. App\Controller\Demo\FooController::action
 *   2. App\Controller\Default\FooController::action  (fallback)
 */
class DynamicControllerResolver
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param string $originalController  FQCN::method, e.g. "App\Controller\Default\DashboardController::index"
     * @param string $tenantSlug          Slug del tenant activo, e.g. "demo"
     * @return string  El controller a usar (puede ser el mismo si no hay override)
     */
    public function resolveControllerFromRoute(string $originalController, string $tenantSlug): string
    {
        // Solo actua sobre controllers en App\Controller\Default\
        if (!str_contains($originalController, 'App\\Controller\\Default\\')) {
            return $originalController;
        }

        // Separar FQCN del metodo
        $parts  = explode('::', $originalController, 2);
        $fqcn   = $parts[0];
        $method = $parts[1] ?? '__invoke';

        // Obtener nombre corto: App\Controller\Default\DashboardController -> DashboardController
        $shortClass = substr($fqcn, strrpos($fqcn, '\\') + 1);

        // Construir slug capitalizado como parte del namespace, e.g. "demo" -> "Demo"
        $slugNamespace = implode('', array_map('ucfirst', explode('-', $tenantSlug)));

        // Candidato: App\Controller\{SlugNamespace}\DashboardController
        $candidate = 'App\\Controller\\' . $slugNamespace . '\\' . $shortClass;

        if (class_exists($candidate) && method_exists($candidate, $method)) {
            $this->logger->debug('[DynamicControllerResolver] Override encontrado', [
                'original' => $originalController,
                'resolved' => $candidate . '::' . $method,
                'tenant' => $tenantSlug,
            ]);

            return $candidate . '::' . $method;
        }

        return $originalController;
    }
}
