<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Main\TenantDb;
use App\Enum\EstadoTurno;
use App\Repository\Tenant\TurnoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:turnos:cerrar-parciales-vencidos',
    description: 'Cierra forzosamente turnos que llevan más de X horas en estado PARCIAL.',
)]
class CerrarTurnosParcialVencidosCommand extends Command
{
    public function __construct(
        private readonly TurnoRepository $turnoRepository,
        private readonly EntityManagerInterface $tenantEm,
        private readonly EntityManagerInterface $defaultEm,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('horas', null, InputOption::VALUE_REQUIRED, 'Horas máximas en estado PARCIAL', '26');
        $this->addOption('all-tenants', null, InputOption::VALUE_NONE, 'Ejecutar el cierre sobre todos los tenants activos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $horas = max(1, (int) $input->getOption('horas'));

        if ($input->getOption('all-tenants')) {
            return $this->cerrarTodosLosTenants($io, $horas);
        }

        $cerrados = $this->cerrarTenantActual($horas);

        if ($cerrados === 0) {
            $io->success('No hay turnos parciales vencidos.');
            return Command::SUCCESS;
        }

        $io->success(sprintf('Se cerraron %d turno(s) parcial(es) vencidos.', $cerrados));

        return Command::SUCCESS;
    }

    private function cerrarTodosLosTenants(SymfonyStyle $io, int $horas): int
    {
        /** @var TenantDb[] $tenants */
        $tenants = $this->defaultEm->getRepository(TenantDb::class)->findBy(['isActive' => true]);

        if ($tenants === []) {
            $io->warning('No hay tenants activos registrados.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Tenants activos encontrados: %d', count($tenants)));

        $total = 0;
        $rows = [];

        foreach ($tenants as $tenant) {
            try {
                $this->eventDispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
                $this->tenantEm->clear();

                $cerrados = $this->cerrarTenantActual($horas);
                $total += $cerrados;

                $rows[] = [
                    $tenant->getName() ?? $tenant->getSlug(),
                    $tenant->getDatabaseName(),
                    (string) $cerrados,
                ];
            } catch (\Throwable $e) {
                $rows[] = [
                    $tenant->getName() ?? $tenant->getSlug(),
                    $tenant->getDatabaseName(),
                    'error: ' . $e->getMessage(),
                ];
            }
        }

        $io->table(['Tenant', 'Base de datos', 'Turnos cerrados'], $rows);
        $io->success(sprintf('Cierre automático finalizado. Total de turnos cerrados: %d.', $total));

        return Command::SUCCESS;
    }

    private function cerrarTenantActual(int $horas): int
    {
        $turnos = $this->turnoRepository->findParcialesVencidos($horas);

        $cerrados = 0;
        foreach ($turnos as $turno) {
            $terminoDatetime = \DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $turno->getFecha()->format('Y-m-d') . ' ' . $turno->getHoraTermino()->format('H:i:s'),
            );

            if (!$terminoDatetime instanceof \DateTime) {
                $terminoDatetime = new \DateTime();
            }

            $turno->setRegistroTermino($terminoDatetime)
                ->setEstado(EstadoTurno::COMPLETADO)
                ->setObservaciones(
                    trim(($turno->getObservaciones() ?? '') .
                        ' [Cierre automático por sistema el ' . (new \DateTime())->format('d/m/Y H:i') . ']')
                );

            $cerrados++;
        }

        if ($cerrados > 0) {
            $this->tenantEm->flush();
        }

        return $cerrados;
    }
}
