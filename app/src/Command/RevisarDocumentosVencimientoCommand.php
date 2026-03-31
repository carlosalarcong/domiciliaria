<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\DocumentoVencimientoMessage;
use App\Repository\DocumentoTrabajadorRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:revisar-documentos-vencimiento',
    description: 'Notifica sobre documentos de trabajadores próximos a vencer (30 y 7 días)',
)]
final class RevisarDocumentosVencimientoCommand extends Command
{
    public function __construct(
        private readonly DocumentoTrabajadorRepository $documentoRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $documentos = $this->documentoRepository->findProximosAVencer(30);

        $count = 0;
        foreach ($documentos as $doc) {
            $vence = $doc->getFechaVencimiento();
            $dias  = (int) (new \DateTime('today'))->diff($vence)->days;

            // Solo notificar a los 30, 15, 7, 3 y 1 días para evitar spam diario
            if (!in_array($dias, [30, 15, 7, 3, 1], true)) {
                continue;
            }

            $trabajador = $doc->getTrabajador();
            $this->bus->dispatch(new DocumentoVencimientoMessage(
                documentoId:       (string) $doc->getId(),
                trabajadorId:      (string) $trabajador->getId(),
                trabajadorNombre:  $trabajador->getNombreCompleto(),
                tipoDocumento:     $doc->getTipo()->etiqueta(),
                descripcion:       $doc->getDescripcion() ?? '',
                fechaVencimiento:  $vence->format('d/m/Y'),
                diasRestantes:     $dias,
            ));
            $count++;
        }

        $output->writeln(sprintf('Revisión completada: %d notificación(es) despachada(s).', $count));

        return Command::SUCCESS;
    }
}
