<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DocumentoVencimientoMessage;
use App\Repository\UserRepository;
use App\Service\NotificacionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class DocumentoVencimientoHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly NotificacionService $notificacionService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(DocumentoVencimientoMessage $message): void
    {
        $this->logger->info('Documento próximo a vencer', [
            'documento_id' => $message->documentoId,
            'trabajador'   => $message->trabajadorNombre,
            'tipo'         => $message->tipoDocumento,
            'vence_en'     => $message->fechaVencimiento,
            'dias'         => $message->diasRestantes,
        ]);

        $destinatarios = $this->userRepository->findActivosByRoles(['ROLE_ADMIN', 'ROLE_COORDINADOR']);

        $url = $this->urlGenerator->generate(
            'app_trabajador_show',
            ['id' => $message->trabajadorId, 'tab' => 'documentos'],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        $titulo  = "Doc. por vencer — {$message->trabajadorNombre}";
        $cuerpo  = "{$message->tipoDocumento} · vence {$message->fechaVencimiento} ({$message->diasRestantes}d)";

        $this->notificacionService->crearParaVarios(
            $destinatarios,
            'documento_vencimiento',
            $titulo,
            $cuerpo,
            $url,
        );

        $urgencia = $message->diasRestantes <= 7 ? '🔴' : '🟡';
        $asunto   = "{$urgencia} Documento por vencer — {$message->trabajadorNombre} ({$message->diasRestantes} días)";

        foreach ($destinatarios as $usuario) {
            if (!$usuario->getEmail() || !$usuario->isActivo()) {
                continue;
            }

            try {
                $email = (new Email())
                    ->to($usuario->getEmail())
                    ->subject($asunto)
                    ->html(sprintf(
                        '<h2>Documento próximo a vencer</h2>
                        <p>El siguiente documento vencerá en <strong>%d día(s)</strong>:</p>
                        <table cellpadding="6" style="border-collapse:collapse">
                            <tr><td><strong>Trabajador:</strong></td><td>%s</td></tr>
                            <tr><td><strong>Tipo:</strong></td><td>%s</td></tr>
                            <tr><td><strong>Descripción:</strong></td><td>%s</td></tr>
                            <tr><td><strong>Vence el:</strong></td><td><strong>%s</strong></td></tr>
                        </table>
                        <p style="margin-top:16px">Ingrese al sistema para renovar o reemplazar el documento.</p>',
                        $message->diasRestantes,
                        htmlspecialchars($message->trabajadorNombre),
                        htmlspecialchars($message->tipoDocumento),
                        htmlspecialchars($message->descripcion ?: '—'),
                        htmlspecialchars($message->fechaVencimiento),
                    ));

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Error enviando alerta de vencimiento de documento', [
                    'usuario' => $usuario->getEmail(),
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }
}
