<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;

/**
 * Resuelve tenants desde la BD central sin usar el ORM (conexión DBAL pura).
 * Se usa en el TenantDatabaseSwitchListener antes de que el EM esté disponible.
 */
class TenantResolver
{
    /** Subdominios reservados — no corresponden a ningún tenant */
    private const MAIN_SUBDOMAINS = ['www', 'admin', 'app', 'api', 'localhost', ''];

    private array $centralDbalConfig;

    public function __construct(private readonly string $centralDbUrl)
    {
        $this->centralDbalConfig = $this->parseUrl($centralDbUrl);
    }

    // ─── Resolución por request ──────────────────────────────────────────────

    public function extractSubdomain(string $host): ?string
    {
        $host  = strtolower(explode(':', $host)[0]);
        $parts = explode('.', $host);

        if (count($parts) < 2) {
            return null;
        }

        $subdomain = $parts[0];

        return in_array($subdomain, self::MAIN_SUBDOMAINS, true) ? null : $subdomain;
    }

    // ─── Consultas a BD central ──────────────────────────────────────────────

    public function getTenantBySlug(string $slug): ?array
    {
        $conn = $this->createCentralConnection();
        try {
            $row = $conn->fetchAssociative(
                'SELECT id, name, slug, database_name, database_status, is_active
                 FROM tenant_db WHERE slug = :slug AND is_active = TRUE',
                ['slug' => $slug]
            );
            return $row ?: null;
        } finally {
            $conn->close();
        }
    }

    public function getTenantById(int $id): ?array
    {
        $conn = $this->createCentralConnection();
        try {
            $row = $conn->fetchAssociative(
                'SELECT id, name, slug, database_name, database_status, is_active
                 FROM tenant_db WHERE id = :id AND is_active = TRUE',
                ['id' => $id]
            );
            return $row ?: null;
        } finally {
            $conn->close();
        }
    }

    public function getAllActiveTenants(): array
    {
        $conn = $this->createCentralConnection();
        try {
            return $conn->fetchAllAssociative(
                'SELECT id, name, slug, database_name, database_status
                 FROM tenant_db WHERE is_active = TRUE ORDER BY name'
            );
        } finally {
            $conn->close();
        }
    }

    // ─── Conexión directa a BD de un tenant ─────────────────────────────────

    public function createTenantConnection(array $tenant): Connection
    {
        $config = new \Doctrine\DBAL\Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        return DriverManager::getConnection([
            'driver'        => 'pdo_pgsql',
            'host'          => $_ENV['TENANT_DB_HOST'] ?? 'postgres',
            'port'          => 5432,
            'dbname'        => $tenant['database_name'],
            'user'          => $_ENV['TENANT_DB_USER'] ?? 'app',
            'password'      => $_ENV['TENANT_DB_PASSWORD'] ?? 'secret',
            'charset'       => 'utf8',
            'serverVersion' => '16',
        ], $config);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createCentralConnection(): Connection
    {
        $config = new \Doctrine\DBAL\Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        return DriverManager::getConnection(
            array_merge(['driver' => 'pdo_pgsql'], $this->centralDbalConfig),
            $config
        );
    }

    private function parseUrl(string $url): array
    {
        // Quitar query string para parse_url limpio
        $cleanUrl = strtok($url, '?');
        $parsed   = parse_url($cleanUrl);

        return [
            'host'          => $parsed['host'] ?? 'postgres',
            'port'          => $parsed['port'] ?? 5432,
            'dbname'        => ltrim($parsed['path'] ?? '/domiciliaria', '/'),
            'user'          => isset($parsed['user']) ? urldecode($parsed['user']) : 'app',
            'password'      => isset($parsed['pass']) ? urldecode($parsed['pass']) : 'secret',
            'charset'       => 'utf8',
            'serverVersion' => '16',
        ];
    }
}
