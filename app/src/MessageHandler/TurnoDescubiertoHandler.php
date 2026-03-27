<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TurnoDescubiertoMessage;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class TurnoDescubiertoHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TurnoDescubiertoMessage $message): void
    {
        $this->logger->warning('Turno descubierto detectado', [
            'turno_id' => $message->turnoId,
            'paciente' => $message->pacienteNombre,
            'fecha'    => $message->fecha,
            'tipo'     => $message->tipoTurno,
        ]);

        // Notificar a admins y coordinadores
        $destinatarios = $this->userRepository->findByRol('ROLE_ADMIN');
        $coordinadores = $this->userRepository->findByRol('ROLE_COORDINADOR');
        $destinatarios = array_merge($destinatarios, $coordinadores);

        foreach ($destinatarios as $usuario) {
            if (!$usuario->getEmail() || !$usuario->isActivo()) {
                continue;
            }

            try {
                $email = (new Email())
                    ->to($usuario->getEmail())
                    ->subject("⚠️ Turno descubierto — {$message->pacienteNombre}")
                    ->html(sprintf(
                        '<h2>Turno sin cobertura</h2>
                        <p>El siguiente turno no tiene trabajador asignado:</p>
                        <ul>
                            <li><strong>Paciente:</strong> %s</li>
                            <li><strong>Fecha:</strong> %s</li>
                            <li><strong>Tipo:</strong> %s</li>
                        </ul>
                        <p>Por favor asigne un trabajador o reemplazo a la brevedad.</p>',
                        htmlspecialchars($message->pacienteNombre),
                        htmlspecialchars($message->fecha),
                        htmlspecialchars($message->tipoTurno),
                    ));

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Error enviando notificación de turno descubierto', [
                    'usuario' => $usuario->getEmail(),
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
