<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PacienteEstadoCambioMessage;
use App\Repository\UserRepository;
use App\Service\NotificacionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class PacienteEstadoCambioHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly NotificacionService $notificacionService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(PacienteEstadoCambioMessage $message): void
    {
        $this->logger->info('Cambio de estado de paciente', [
            'paciente_id'     => $message->pacienteId,
            'paciente'        => $message->pacienteNombre,
            'estado_anterior' => $message->estadoAnterior,
            'estado_nuevo'    => $message->estadoNuevo,
        ]);

        $etiquetas = [
            'ACTIVO'       => 'Activo',
            'SUSPENDIDO'   => 'Suspendido',
            'DADO_DE_BAJA' => 'Dado de baja',
        ];

        $estadoAnteriorLabel = $etiquetas[$message->estadoAnterior] ?? $message->estadoAnterior;
        $estadoNuevoLabel    = $etiquetas[$message->estadoNuevo]    ?? $message->estadoNuevo;

        $iconos = [
            'ACTIVO'       => '🟢',
            'SUSPENDIDO'   => '🟡',
            'DADO_DE_BAJA' => '🔴',
        ];
        $icono = $iconos[$message->estadoNuevo] ?? '⚪';

        $asunto = sprintf('%s Cambio de estado — %s (%s → %s)',
            $icono,
            $message->pacienteNombre,
            $estadoAnteriorLabel,
            $estadoNuevoLabel,
        );

        $destinatarios = $this->userRepository->findActivosByRoles(['ROLE_ADMIN', 'ROLE_COORDINADOR']);

        $url = $this->urlGenerator->generate('app_paciente_show', ['id' => $message->pacienteId], UrlGeneratorInterface::ABSOLUTE_PATH);
        $this->notificacionService->crearParaVarios(
            $destinatarios,
            'paciente_estado',
            "Cambio de estado — {$message->pacienteNombre}",
            "{$estadoAnteriorLabel} → {$estadoNuevoLabel}",
            $url,
        );

        foreach ($destinatarios as $usuario) {
            if (!$usuario->getEmail() || !$usuario->isActivo()) {
                continue;
            }

            try {
                $email = (new Email())
                    ->to($usuario->getEmail())
                    ->subject($asunto)
                    ->html(sprintf(
                        '<h2>Cambio de estado de paciente</h2>
                        <p>Se ha registrado un cambio de estado para el siguiente paciente:</p>
                        <table cellpadding="6" style="border-collapse:collapse">
                            <tr><td><strong>Paciente:</strong></td><td>%s</td></tr>
                            <tr><td><strong>Estado anterior:</strong></td><td>%s</td></tr>
                            <tr><td><strong>Estado nuevo:</strong></td><td><strong>%s</strong></td></tr>
                        </table>
                        <p style="margin-top:16px">Ingrese al sistema para revisar el detalle del paciente.</p>',
                        htmlspecialchars($message->pacienteNombre),
                        htmlspecialchars($estadoAnteriorLabel),
                        htmlspecialchars($estadoNuevoLabel),
                    ));

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Error enviando notificación de cambio de estado de paciente', [
                    'usuario' => $usuario->getEmail(),
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
