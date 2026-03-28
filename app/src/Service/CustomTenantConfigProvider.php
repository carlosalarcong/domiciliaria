<?php

declare(strict_types=1);

namespace App\Service;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;

/**
 * Implementación propia de TenantConfigProviderInterface del hakam bundle.
 * Delega la resolución al TenantResolver (consulta directa DBAL a BD central).
 * Registrado como alias de la interfaz en services.yaml.
 */
class CustomTenantConfigProvider implements TenantConfigProviderInterface
{
    public function __construct(private readonly TenantResolver $tenantResolver)
    {}

    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO
    {
        $tenant = is_numeric($identifier)
            ? $this->tenantResolver->getTenantById((int) $identifier)
            : $this->tenantResolver->getTenantBySlug((string) $identifier);

        if ($tenant === null) {
            throw new \RuntimeException(sprintf('Tenant no encontrado: %s', $identifier));
        }

        $dbStatus = DatabaseStatusEnum::tryFrom((string) ($tenant['database_status'] ?? ''))
            ?? DatabaseStatusEnum::DATABASE_MIGRATED;

        return TenantConnectionConfigDTO::fromArgs(
            identifier: $identifier,
            driver:     DriverTypeEnum::POSTGRES,
            dbStatus:   $dbStatus,
            host:       $_ENV['TENANT_DB_HOST'] ?? 'postgres',
            port:       5432,
            dbname:     $tenant['database_name'],
            user:       $_ENV['TENANT_DB_USER'] ?? 'app',
            password:   $_ENV['TENANT_DB_PASSWORD'] ?? 'secret',
        );
    }
}
