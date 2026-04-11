<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Main\TenantDb;
use App\Enum\EstadoFactura;
use App\Message\MarcarFacturasVencidasMessage;
use App\Repository\FacturaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MarcarFacturasVencidasHandler
{
    public function __construct(
        private readonly FacturaRepository $facturaRepository,
        private readonly EntityManagerInterface $tenantEm,
        private readonly EntityManagerInterface $defaultEm,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(MarcarFacturasVencidasMessage $message): void
    {
        if ($message->allTenants) {
            /** @var TenantDb[] $tenants */
            $tenants = $this->defaultEm->getRepository(TenantDb::class)->findBy(['isActive' => true]);

            foreach ($tenants as $tenant) {
                try {
                    $this->eventDispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
                    $this->tenantEm->clear();
                    $marcadas = $this->procesarTenantActual();

                    $this->logger->info('Facturas marcadas como vencidas.', [
                        'tenant_id' => $tenant->getId(),
                        'marcadas'  => $marcadas,
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Error al marcar facturas vencidas.', [
                        'tenant_id' => $tenant->getId(),
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            return;
        }

        $this->procesarTenantActual();
    }

    private function procesarTenantActual(): int
    {
        $facturas = $this->facturaRepository->findVencidas();

        foreach ($facturas as $factura) {
            $factura->setEstado(EstadoFactura::VENCIDA);
        }

        if (count($facturas) > 0) {
            $this->tenantEm->flush();
        }

        return count($facturas);
    }
}
