<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Main\TenantDb;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tenant:crear',
    description: 'Registra un nuevo tenant, crea su BD y ejecuta las migraciones',
)]
class TenantCrearCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $centralDbUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('nombre',     InputArgument::REQUIRED, 'Nombre de la clínica')
            ->addArgument('subdominio', InputArgument::REQUIRED, 'Subdominio (slug único, ej: demo)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $nombre    = trim($input->getArgument('nombre'));
        $subdominio = strtolower(trim($input->getArgument('subdominio')));
        $dbName    = 'clinica_' . preg_replace('/[^a-z0-9_]/', '_', $subdominio);

        $io->title('Crear Tenant: ' . $nombre);

        // ── 1. Verificar subdominio único ─────────────────────────────────────
        $existing = $this->em->getRepository(TenantDb::class)->findOneBy(['slug' => $subdominio]);
        if ($existing !== null) {
            $io->error(sprintf('El subdominio "%s" ya está registrado.', $subdominio));
            return Command::FAILURE;
        }

        // ── 2. Crear BD en PostgreSQL (con check en pg_database) ──────────────
        $io->section('Creando base de datos: ' . $dbName);
        try {
            $this->createDatabase($dbName);
            $io->text('  ✓ Base de datos creada.');
        } catch (\Throwable $e) {
            $io->error('Error creando la BD: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // ── 3. Registrar en BD central ────────────────────────────────────────
        $io->section('Registrando tenant en BD central');
        $tenant = new TenantDb();
        $tenant->setSlug($subdominio)
               ->setName($nombre)
               ->setDatabaseName($dbName)
               ->setDatabaseStatus(DatabaseStatusEnum::DATABASE_CREATED)
               ->setIsActive(true);

        $this->em->persist($tenant);
        $this->em->flush();
        $io->text(sprintf('  ✓ Tenant registrado con ID: %d', $tenant->getId()));

        // ── 4. Ejecutar migraciones en la BD del tenant ───────────────────────
        $io->section('Ejecutando migraciones del tenant');
        try {
            $exitCode = $this->getApplication()
                ->find('tenant:migrations:migrate')
                ->run(
                    new ArrayInput([
                        '--dbid'           => (string) $tenant->getId(),
                        '--no-interaction' => true,
                    ]),
                    $output
                );

            if ($exitCode === Command::SUCCESS) {
                $tenant->setDatabaseStatus(DatabaseStatusEnum::DATABASE_MIGRATED);
                $this->em->flush();
                $io->text('  ✓ Migraciones ejecutadas.');
            } else {
                $io->warning('Las migraciones finalizaron con código: ' . $exitCode);
            }
        } catch (\Throwable $e) {
            $io->warning('Error en migraciones: ' . $e->getMessage() . ' — el tenant fue registrado pero puede requerir migración manual.');
        }

        // ── 5. Resumen ────────────────────────────────────────────────────────
        $io->success('Tenant creado correctamente.');
        $io->table(
            ['Campo', 'Valor'],
            [
                ['ID',         $tenant->getId()],
                ['Nombre',     $nombre],
                ['Subdominio', $subdominio],
                ['BD',         $dbName],
                ['URL local',  sprintf('http://%s.localhost:8090', $subdominio)],
            ]
        );

        return Command::SUCCESS;
    }

    private function createDatabase(string $dbName): void
    {
        // Parsear DATABASE_URL para conectar a PostgreSQL sin especificar dbname
        $cleanUrl = strtok($this->centralDbUrl, '?');
        $parsed   = parse_url($cleanUrl);

        $config = new \Doctrine\DBAL\Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        // Conectar al sistema (postgres) para poder crear la BD
        $conn = DriverManager::getConnection([
            'driver'        => 'pdo_pgsql',
            'host'          => $parsed['host'] ?? 'postgres',
            'port'          => $parsed['port'] ?? 5432,
            'dbname'        => 'postgres', // BD de sistema para el CREATE DATABASE
            'user'          => isset($parsed['user']) ? urldecode($parsed['user']) : 'app',
            'password'      => isset($parsed['pass']) ? urldecode($parsed['pass']) : 'secret',
            'charset'       => 'utf8',
        ], $config);

        try {
            // PostgreSQL no tiene CREATE DATABASE IF NOT EXISTS — verificar primero
            $exists = $conn->fetchOne(
                'SELECT 1 FROM pg_database WHERE datname = :name',
                ['name' => $dbName]
            );

            if (!$exists) {
                // Los identificadores de BD no admiten parámetros bind — usar escape manual
                $safeName = preg_replace('/[^a-z0-9_]/', '', $dbName);
                $conn->executeStatement('CREATE DATABASE ' . $safeName);
            }
        } finally {
            $conn->close();
        }
    }
}
