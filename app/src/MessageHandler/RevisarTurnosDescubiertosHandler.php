<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RevisarTurnosDescubiertosMessage;
use App\Message\TurnoDescubiertoMessage;
use App\Repository\TurnoRepository;
use App\Service\ConfiguracionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class RevisarTurnosDescubiertosHandler
{
    public function __construct(
        private readonly TurnoRepository $turnoRepository,
        private readonly ConfiguracionService $configuracionService,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(RevisarTurnosDescubiertosMessage $message): void
    {
        $dias = $this->configuracionService->get()->getDiasAnticipacionAlertas();
        $turnos = $this->turnoRepository->findDescubiertosProximosDias($dias);

        $this->logger->info(sprintf('Revisión diaria: %d turno(s) descubierto(s) para mañana.', count($turnos)));

        $ahora = new \DateTimeImmutable();

        foreach ($turnos as $turno) {
            $ultimaAlerta = $turno->getUltimaAlertaDescubiertoEn();
            if ($ultimaAlerta !== null) {
                $diferencia = $ahora->diff($ultimaAlerta);
                $horasDesdeUltimaAlerta = ($diferencia->days * 24) + $diferencia->h;
                if ($horasDesdeUltimaAlerta < 20) {
                    continue;
                }
            }

            $this->bus->dispatch(new TurnoDescubiertoMessage(
                turnoId:        (string) $turno->getId(),
                pacienteNombre: $turno->getPaciente()?->getNombreCompleto() ?? 'Desconocido',
                fecha:          $turno->getFecha()->format('d/m/Y'),
                tipoTurno:      $turno->getTipoTurno()->etiqueta(),
            ));
        }
    }
}
