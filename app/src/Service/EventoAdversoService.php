<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\EventoAdverso;
use App\Entity\Tenant\SeguimientoEvento;
use App\Entity\Tenant\User;
use App\Enum\EstadoEvento;
use App\Message\EventoAdversoGraveMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EventoAdversoService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    public function registrar(EventoAdverso $evento, User $creadoPor): EventoAdverso
    {
        $evento->setCreadoPor($creadoPor);
        $evento->setEstado(EstadoEvento::ABIERTO);

        $this->em->persist($evento);
        $this->em->flush();

        if ($evento->getGravedad()->requiereNotificacion()) {
            $this->despacharAlerta($evento);
        }

        return $evento;
    }

    public function actualizar(EventoAdverso $evento): EventoAdverso
    {
        $this->em->flush();

        return $evento;
    }

    public function agregarSeguimiento(EventoAdverso $evento, string $nota, User $autor): SeguimientoEvento
    {
        $seguimiento = new SeguimientoEvento();
        $seguimiento->setEvento($evento)
                    ->setNota($nota)
                    ->setCreadoPor($autor);

        if ($evento->getEstado() === EstadoEvento::ABIERTO) {
            $evento->setEstado(EstadoEvento::EN_PROCESO);
        }

        $this->em->persist($seguimiento);
        $this->em->flush();

        return $seguimiento;
    }

    public function cerrar(EventoAdverso $evento, string $observacion, User $autor): EventoAdverso
    {
        $evento->setEstado(EstadoEvento::CERRADO)
               ->setFechaCierre(new \DateTime())
               ->setObservacionCierre($observacion);

        // Agregar seguimiento de cierre
        $seguimiento = new SeguimientoEvento();
        $seguimiento->setEvento($evento)
                    ->setNota('Evento cerrado. ' . $observacion)
                    ->setCreadoPor($autor);

        $this->em->persist($seguimiento);
        $this->em->flush();

        return $evento;
    }

    private function despacharAlerta(EventoAdverso $evento): void
    {
        $this->bus->dispatch(new EventoAdversoGraveMessage(
            eventoId:       (string) $evento->getId(),
            pacienteNombre: $evento->getPaciente()?->getNombreCompleto() ?? 'Desconocido',
            tipo:           $evento->getTipo()->etiqueta(),
            gravedad:       $evento->getGravedad()->value,
            fecha:          $evento->getFechaEvento()?->format('d/m/Y') ?? '—',
            descripcion:    mb_substr($evento->getDescripcion(), 0, 200),
        ));
    }
}
