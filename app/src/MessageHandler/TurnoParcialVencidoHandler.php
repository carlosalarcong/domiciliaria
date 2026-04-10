<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TurnoParcialVencidoMessage;
use App\Repository\UserRepository;
use App\Service\NotificacionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class TurnoParcialVencidoHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NotificacionService $notificacionService,
        private readonly LoggerInterface $logger,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(TurnoParcialVencidoMessage $message): void
    {
        $this->logger->warning('Turno parcial vencido detectado', [
            'turno_id'   => $message->turnoId,
            'paciente'   => $message->pacienteNombre,
            'trabajador' => $message->trabajadorNombre,
            'fecha'      => $message->fecha,
        ]);

        $url = $this->urlGenerator->generate(
            'app_turno_show',
            ['id' => $message->turnoId],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        $destinatarios = $this->userRepository->findActivosByRoles(['ROLE_ADMIN', 'ROLE_COORDINADOR']);

        $this->notificacionService->crearParaVarios(
            $destinatarios,
            'turno_parcial_vencido',
            "Turno parcial vencido — {$message->pacienteNombre}",
            "{$message->fecha} · {$message->tipoTurno} · Trabajador: {$message->trabajadorNombre}",
            $url,
        );
    }
}
