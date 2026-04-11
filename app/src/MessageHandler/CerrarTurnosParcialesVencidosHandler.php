<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Main\TenantDb;
use App\Enum\EstadoTurno;
use App\Message\CerrarTurnosParcialesVencidosMessage;
use App\Repository\Tenant\TurnoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CerrarTurnosParcialesVencidosHandler
{
    public function __construct(
        private readonly TurnoRepository $turnoRepository,
        private readonly EntityManagerInterface $tenantEm,
        private readonly EntityManagerInterface $defaultEm,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(CerrarTurnosParcialesVencidosMessage $message): void
    {
        if ($message->allTenants) {
            /** @var TenantDb[] $tenants */
            $tenants = $this->defaultEm->getRepository(TenantDb::class)->findBy(['isActive' => true]);

            foreach ($tenants as $tenant) {
                try {
                    $this->eventDispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
                    $this->tenantEm->clear();
                    $cerrados = $this->cerrarTenantActual($message->horas);

                    $this->logger->info('Cierre automático de turnos parciales ejecutado por scheduler.', [
                        'tenant_id' => $tenant->getId(),
                        'tenant_db' => $tenant->getDatabaseName(),
                        'cerrados' => $cerrados,
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Error cerrando turnos parciales vencidos por scheduler.', [
                        'tenant_id' => $tenant->getId(),
                        'tenant_db' => $tenant->getDatabaseName(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return;
        }

        $cerrados = $this->cerrarTenantActual($message->horas);
        $this->logger->info('Cierre automático de turnos parciales ejecutado por scheduler (tenant actual).', [
            'cerrados' => $cerrados,
        ]);
    }

    private function cerrarTenantActual(int $horas): int
    {
        $turnos = $this->turnoRepository->findParcialesVencidos(max(1, $horas));

        $cerrados = 0;
        foreach ($turnos as $turno) {
            if ($turno->getRegistroInicio() !== null) {
                $turno->setRegistroTermino(new \DateTime());
            } else {
                $turno->setRegistroTermino(null)
                    ->setObservaciones(
                        trim(($turno->getObservaciones() ?? '') . ' [Sin registro de inicio - se uso duracion planificada]')
                    );
            }

            $turno->setEstado(EstadoTurno::COMPLETADO)
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
