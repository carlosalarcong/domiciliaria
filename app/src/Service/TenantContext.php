<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Mantiene el tenant activo para el ciclo de vida del request.
 * Cache dual: memoria (por request) + sesión (entre sub-requests).
 */
class TenantContext
{
    private ?array $currentTenant = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $centralDbUrl,
    ) {}

    // ─── Escritura ────────────────────────────────────────────────────────────

    public function setCurrentTenant(?array $tenant): void
    {
        $this->currentTenant = $tenant;

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if ($tenant !== null) {
            $session->set('tenant', $tenant);
            $session->set('tenant_id',       $tenant['id']);
            $session->set('tenant_name',     $tenant['name'] ?? '');
            $session->set('tenant_slug',     $tenant['slug'] ?? '');
            $session->set('database_name',   $tenant['database_name'] ?? '');
        } else {
            foreach (['tenant', 'tenant_id', 'tenant_name', 'tenant_slug', 'database_name'] as $key) {
                $session->remove($key);
            }
        }
    }

    public function clear(): void
    {
        $this->currentTenant = null;
    }

    // ─── Lectura ──────────────────────────────────────────────────────────────

    public function getCurrentTenant(): ?array
    {
        // 1. Memoria
        if ($this->currentTenant !== null) {
            return $this->currentTenant;
        }

        // 2. Sesión completa
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null && $request->hasSession()) {
            $session = $request->getSession();
            $tenant  = $session->get('tenant');
            if (is_array($tenant)) {
                $this->currentTenant = $tenant;
                return $this->currentTenant;
            }

            // 3. Reconstruir desde claves individuales
            $id = $session->get('tenant_id');
            if ($id !== null) {
                $this->currentTenant = [
                    'id'            => $id,
                    'name'          => $session->get('tenant_name', ''),
                    'slug'          => $session->get('tenant_slug', ''),
                    'database_name' => $session->get('database_name', ''),
                    'database_status' => 'DATABASE_MIGRATED',
                    'is_active'     => true,
                ];
                return $this->currentTenant;
            }
        }

        return null;
    }

    // ─── Accesores derivados ──────────────────────────────────────────────────

    public function hasCurrentTenant(): bool
    {
        return $this->getCurrentTenant() !== null;
    }

    public function getCurrentSubdomain(): ?string
    {
        return $this->getCurrentTenant()['slug'] ?? null;
    }

    public function getCurrentTenantName(): ?string
    {
        return $this->getCurrentTenant()['name'] ?? null;
    }

    public function getCurrentDatabaseName(): ?string
    {
        return $this->getCurrentTenant()['database_name'] ?? null;
    }
}
