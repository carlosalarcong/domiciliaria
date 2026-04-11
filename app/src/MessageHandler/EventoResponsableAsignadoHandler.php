<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\EventoResponsableAsignadoMessage;
use App\Repository\Tenant\UserRepository;
use App\Service\NotificacionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class EventoResponsableAsignadoHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly NotificacionService $notificacionService,
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(EventoResponsableAsignadoMessage $message): void
    {
        $this->logger->info('Responsable asignado a evento adverso', [
            'evento_id'   => $message->eventoId,
            'responsable' => $message->responsableNombre,
        ]);

        $url = $this->urlGenerator->generate('app_evento_show', ['id' => $message->eventoId], UrlGeneratorInterface::ABSOLUTE_PATH);
        $responsable = $this->userRepository->findOneBy(['email' => $message->responsableEmail]);
        if ($responsable !== null) {
            $this->notificacionService->crear(
                $responsable,
                'responsable_asignado',
                "Se te asignó un evento — {$message->pacienteNombre}",
                "{$message->tipo} · {$message->gravedad}",
                $url,
            );
        }

        try {
            $email = (new Email())
                ->to($message->responsableEmail)
                ->subject("📋 Se te asignó un evento adverso — {$message->pacienteNombre}")
                ->html(sprintf(
                    '<h2>📋 Evento adverso asignado</h2>
                    <p>Hola <strong>%s</strong>, se te ha asignado como responsable del siguiente evento:</p>
                    <ul>
                        <li><strong>Paciente:</strong> %s</li>
                        <li><strong>Tipo:</strong> %s</li>
                        <li><strong>Gravedad:</strong> %s</li>
                        <li><strong>Fecha:</strong> %s</li>
                    </ul>
                    <p>Por favor ingrese al sistema para gestionar el seguimiento de este evento.</p>',
                    htmlspecialchars($message->responsableNombre),
                    htmlspecialchars($message->pacienteNombre),
                    htmlspecialchars($message->tipo),
                    htmlspecialchars($message->gravedad),
                    htmlspecialchars($message->fecha),
                ));

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Error enviando notificación de responsable asignado', [
                'responsable' => $message->responsableEmail,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
