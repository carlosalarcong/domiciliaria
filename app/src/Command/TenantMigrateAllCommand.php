<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Main\TenantDb;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tenant:migrate-all',
    description: 'Aplica migraciones pendientes a todos los tenants activos',
)]
class TenantMigrateAllCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Solo listar tenants sin ejecutar migraciones');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Migraciones multi-tenant');

        /** @var TenantDb[] $tenants */
        $tenants = $this->em->getRepository(TenantDb::class)->findBy(['isActive' => true]);

        if (empty($tenants)) {
            $io->warning('No hay tenants activos registrados.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Tenants activos encontrados: %d', count($tenants)));

        $results = [];

        foreach ($tenants as $tenant) {
            if ($dryRun) {
                $io->text(sprintf('  [dry-run] %s → %s', $tenant->getName() ?? $tenant->getSlug(), $tenant->getDatabaseName()));
                $results[] = [$tenant->getName() ?? $tenant->getSlug(), $tenant->getDatabaseName(), 'pendiente (dry-run)'];
                continue;
            }

            $io->text(sprintf('  Migrando: %s → %s ...', $tenant->getName() ?? $tenant->getSlug(), $tenant->getDatabaseName()));

            try {
                $exitCode = $this->getApplication()
                    ->find('tenant:migrations:migrate')
                    ->run(
                        new ArrayInput([
                            'type'             => 'migrate',
                            'dbId'             => (string) $tenant->getId(),
                            '--no-interaction' => true,
                        ]),
                        new NullOutput()
                    );

                $status = $exitCode === Command::SUCCESS ? '✓ ok' : '⚠ error (' . $exitCode . ')';
                $results[] = [$tenant->getName() ?? $tenant->getSlug(), $tenant->getDatabaseName(), $status];
            } catch (\Throwable $e) {
                $results[] = [$tenant->getName() ?? $tenant->getSlug(), $tenant->getDatabaseName(), '✗ ' . $e->getMessage()];
                $io->text('    Error: ' . $e->getMessage());
            }
        }

        $io->table(['Tenant', 'Base de datos', 'Estado'], $results);

        $io->success('Proceso completado.');

        return Command::SUCCESS;
    }
}
