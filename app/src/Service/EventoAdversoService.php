<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\EventoAdverso;
use App\Entity\Tenant\SeguimientoEvento;
use App\Entity\Tenant\User;
use App\Enum\EstadoEvento;
use App\Message\EventoAdversoGraveMessage;
use App\Message\EventoResponsableAsignadoMessage;
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

    public function actualizar(EventoAdverso $evento, ?User $responsableAnterior = null): EventoAdverso
    {
        $this->em->flush();

        $responsableNuevo = $evento->getResponsable();
        if ($responsableNuevo !== null && $responsableNuevo !== $responsableAnterior) {
            $this->despacharNotificacionResponsable($evento, $responsableNuevo);
        }

        return $evento;
    }

    public function ponerEnRevision(EventoAdverso $evento, User $autor): EventoAdverso
    {
        if (!$evento->getEstado()->puedePonerEnRevision()) {
            throw new \LogicException('Solo se puede poner en revisión un evento en estado Registrado.');
        }

        $evento->setEstado(EstadoEvento::EN_PROCESO)
               ->setRevisadoPor($autor)
               ->setRevisadoEn(new \DateTimeImmutable());

        $seguimiento = new SeguimientoEvento();
        $seguimiento->setEvento($evento)
                    ->setNota('Evento puesto en revisión por ' . $autor->getNombreCompleto() . '.')
                    ->setCreadoPor($autor);

        $this->em->persist($seguimiento);
        $this->em->flush();

        return $evento;
    }

    public function agregarSeguimiento(EventoAdverso $evento, string $nota, User $autor): SeguimientoEvento
    {
        if ($evento->getEstado() === EstadoEvento::CERRADO) {
            throw new \LogicException('No se puede agregar seguimiento a un evento cerrado.');
        }

        $seguimiento = new SeguimientoEvento();
        $seguimiento->setEvento($evento)
                    ->setNota($nota)
                    ->setCreadoPor($autor);

        $this->em->persist($seguimiento);
        $this->em->flush();

        return $seguimiento;
    }

    public function cerrar(EventoAdverso $evento, string $observacion, User $autor): EventoAdverso
    {
        if (!$evento->getEstado()->puedeCerrar()) {
            throw new \LogicException('Solo se puede cerrar un evento que está en revisión.');
        }

        $evento->setEstado(EstadoEvento::CERRADO)
               ->setFechaCierre(new \DateTime())
               ->setObservacionCierre($observacion)
               ->setCerradoPor($autor);

        $seguimiento = new SeguimientoEvento();
        $seguimiento->setEvento($evento)
                    ->setNota('Evento cerrado. ' . $observacion)
                    ->setCreadoPor($autor);

        $this->em->persist($seguimiento);
        $this->em->flush();

        return $evento;
    }

    private function despacharNotificacionResponsable(EventoAdverso $evento, User $responsable): void
    {
        if (!$responsable->getEmail() || !$responsable->isActivo()) {
            return;
        }

        $this->bus->dispatch(new EventoResponsableAsignadoMessage(
            eventoId:          (string) $evento->getId(),
            pacienteNombre:    $evento->getPaciente()?->getNombreCompleto() ?? 'Desconocido',
            tipo:              $evento->getTipo()->etiqueta(),
            gravedad:          $evento->getGravedad()->etiqueta(),
            fecha:             $evento->getFechaEvento()?->format('d/m/Y') ?? '—',
            responsableEmail:  $responsable->getEmail(),
            responsableNombre: $responsable->getNombreCompleto(),
        ));
    }

    private function despacharAlerta(EventoAdverso $evento): void
    {
        $this->bus->dispatch(new EventoAdversoGraveMessage(
            eventoId:       (string) $evento->getId(),
            pacienteNombre: $evento->getPaciente()?->getNombreCompleto() ?? 'Desconocido',
            tipo:           $evento->getTipo()->etiqueta(),
            gravedad:       $evento->getGravedad()->etiqueta(),
            fecha:          $evento->getFechaEvento()?->format('d/m/Y') ?? '—',
            descripcion:    mb_substr($evento->getDescripcion(), 0, 200),
        ));
    }
}
