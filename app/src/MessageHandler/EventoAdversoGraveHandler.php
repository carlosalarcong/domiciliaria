<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\EventoAdversoGraveMessage;
use App\Repository\Tenant\UserRepository;
use App\Service\NotificacionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class EventoAdversoGraveHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly NotificacionService $notificacionService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(EventoAdversoGraveMessage $message): void
    {
        $this->logger->warning('Evento adverso grave/crítico registrado', [
            'evento_id' => $message->eventoId,
            'paciente'  => $message->pacienteNombre,
            'gravedad'  => $message->gravedad,
            'tipo'      => $message->tipo,
        ]);

        $destinatarios = $this->userRepository->findActivosByRoles(['ROLE_ADMIN', 'ROLE_COORDINADOR']);

        $url = $this->urlGenerator->generate('app_evento_show', ['id' => $message->eventoId], UrlGeneratorInterface::ABSOLUTE_PATH);
        $this->notificacionService->crearParaVarios(
            $destinatarios,
            'evento_grave',
            "Evento {$message->gravedad} — {$message->pacienteNombre}",
            "{$message->tipo} · {$message->fecha}",
            $url,
        );

        foreach ($destinatarios as $usuario) {
            if (!$usuario->getEmail() || !$usuario->isActivo()) {
                continue;
            }

            try {
                $email = (new Email())
                    ->to($usuario->getEmail())
                    ->subject("🚨 Evento adverso {$message->gravedad} — {$message->pacienteNombre}")
                    ->html(sprintf(
                        '<h2>⚠️ Evento adverso %s</h2>
                        <p>Se ha registrado un evento adverso que requiere atención:</p>
                        <ul>
                            <li><strong>Paciente:</strong> %s</li>
                            <li><strong>Tipo:</strong> %s</li>
                            <li><strong>Gravedad:</strong> <strong style="color:%s">%s</strong></li>
                            <li><strong>Fecha:</strong> %s</li>
                            <li><strong>Descripción:</strong> %s</li>
                        </ul>
                        <p>Por favor ingrese al sistema para revisar y gestionar este evento.</p>',
                        htmlspecialchars($message->gravedad),
                        htmlspecialchars($message->pacienteNombre),
                        htmlspecialchars($message->tipo),
                        $message->gravedad === 'Crítico' ? '#dc3545' : '#fd7e14',
                        htmlspecialchars($message->gravedad),
                        htmlspecialchars($message->fecha),
                        htmlspecialchars($message->descripcion),
                    ));

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Error enviando alerta de evento adverso', [
                    'usuario' => $usuario->getEmail(),
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
