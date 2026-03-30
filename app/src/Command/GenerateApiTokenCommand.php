<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Main\TenantDb;
use App\Entity\Tenant\ApiToken;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Genera un API token para un tenant específico.
 *
 * Uso:
 *   php bin/console app:api:generar-token demo "Sistema ERP" --permisos=pacientes,turnos,facturas
 *   php bin/console app:api:generar-token demo "RRHH Externo" --permisos=trabajadores,liquidaciones
 *   php bin/console app:api:generar-token demo "Solo lectura" --permisos=pacientes,turnos
 *
 * Permisos disponibles: pacientes, turnos, trabajadores, liquidaciones, facturas
 */
#[AsCommand(
    name: 'app:api:generar-token',
    description: 'Genera un API token para integración externa con un tenant.',
)]
class GenerateApiTokenCommand extends Command
{
    private const PERMISOS_VALIDOS = ['pacientes', 'turnos', 'trabajadores', 'liquidaciones', 'facturas'];

    public function __construct(
        private readonly EntityManagerInterface   $defaultEm,
        private readonly EntityManagerInterface   $tenantEm,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('subdominio', InputArgument::REQUIRED, 'Subdominio del tenant (ej: demo, norte)')
            ->addArgument('nombre', InputArgument::REQUIRED, 'Nombre descriptivo del sistema externo')
            ->addOption('permisos', null, InputOption::VALUE_REQUIRED,
                'Recursos permitidos separados por coma: ' . implode(', ', self::PERMISOS_VALIDOS),
                implode(',', self::PERMISOS_VALIDOS)
            )
            ->addOption('expires', null, InputOption::VALUE_REQUIRED,
                'Fecha de expiración (ej: +1 year, 2027-12-31). Sin valor = sin expiración.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $subdominio = $input->getArgument('subdominio');
        $nombre     = $input->getArgument('nombre');

        // Validar permisos
        $permisosInput = array_map('trim', explode(',', $input->getOption('permisos')));
        $permisosInvalidos = array_diff($permisosInput, self::PERMISOS_VALIDOS);
        if (!empty($permisosInvalidos)) {
            $io->error('Permisos inválidos: ' . implode(', ', $permisosInvalidos));
            $io->writeln('Válidos: ' . implode(', ', self::PERMISOS_VALIDOS));
            return Command::FAILURE;
        }

        // Buscar tenant
        $tenant = $this->defaultEm->getRepository(TenantDb::class)
            ->findOneBy(['slug' => $subdominio]);

        if ($tenant === null) {
            $io->error("Tenant '{$subdominio}' no encontrado.");
            return Command::FAILURE;
        }

        // Cambiar al DB del tenant
        $this->eventDispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
        $this->tenantEm->clear();

        // Generar token seguro
        $plainToken = bin2hex(random_bytes(32)); // 64 chars hex = 256 bits de entropía

        // Parsear expiración
        $expiresAt = null;
        if ($expiresOption = $input->getOption('expires')) {
            try {
                $expiresAt = new \DateTimeImmutable($expiresOption);
            } catch (\Exception) {
                $io->error("Formato de fecha inválido: {$expiresOption}");
                return Command::FAILURE;
            }
        }

        $apiToken = new ApiToken();
        $apiToken->setNombre($nombre)
                 ->setTokenHash(ApiToken::hashToken($plainToken))
                 ->setPermisos($permisosInput)
                 ->setActivo(true)
                 ->setExpiresAt($expiresAt);

        $this->tenantEm->persist($apiToken);
        $this->tenantEm->flush();

        $io->success('Token generado correctamente.');
        $io->table(
            ['Campo', 'Valor'],
            [
                ['Tenant',    $tenant->getName() ?? $subdominio],
                ['ID token',  (string) $apiToken->getId()],
                ['Nombre',    $nombre],
                ['Permisos',  implode(', ', $permisosInput)],
                ['Expira',    $expiresAt?->format('Y-m-d') ?? 'Nunca'],
                ['TOKEN',     $plainToken],
            ]
        );

        $io->warning('Guarda el TOKEN ahora — no se puede recuperar después.');
        $io->writeln('Uso: <info>curl -H "X-API-KEY: ' . $plainToken . '" http://' . $subdominio . '.localhost:8090/api/v1/pacientes</info>');

        return Command::SUCCESS;
    }
}
