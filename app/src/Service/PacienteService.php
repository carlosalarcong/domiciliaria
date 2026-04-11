<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\BitacoraOperativa;
use App\Entity\Tenant\CondicionDomicilio;
use App\Entity\Tenant\HistorialComunicacion;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\User;
use App\Enum\EstadoPaciente;
use App\Enum\EstadoTurno;
use App\Enum\TipoBitacora;
use App\Enum\TipoComunicacion;
use App\Message\PacienteEstadoCambioMessage;
use App\Message\TurnoDescubiertoMessage;
use App\Repository\PacienteRepository;
use App\Repository\TurnoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PacienteService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PacienteRepository $pacienteRepository,
        private readonly TurnoRepository $turnoRepository,
        private readonly MessageBusInterface $bus,
    ) {}

    public function registrar(Paciente $paciente): Paciente
    {
        // Inicializar condición de domicilio vacía
        if ($paciente->getCondicionDomicilio() === null) {
            $condicion = new CondicionDomicilio();
            $paciente->setCondicionDomicilio($condicion);
        }

        $this->em->persist($paciente);
        $this->em->flush();

        return $paciente;
    }

    public function actualizar(Paciente $paciente): Paciente
    {
        $this->em->flush();
        return $paciente;
    }

    public function actualizarCondicion(Paciente $paciente, CondicionDomicilio $condicion): void
    {
        $this->em->flush();
    }

    public function actualizarEstado(Paciente $paciente, EstadoPaciente $nuevoEstado, User $usuario): Paciente
    {
        $estadoAnterior = $paciente->getEstado();

        if ($estadoAnterior === $nuevoEstado) {
            return $paciente;
        }

        // Al dar de baja, se debe registrar fecha de término
        if ($nuevoEstado === EstadoPaciente::DADO_DE_BAJA && $paciente->getFechaTermino() === null) {
            $paciente->setFechaTermino(new \DateTime());
        }

        $paciente->setEstado($nuevoEstado);

        $descripcion = match ($nuevoEstado) {
            EstadoPaciente::DADO_DE_BAJA => sprintf(
                'Paciente dado de baja. Estado anterior: %s.',
                $estadoAnterior->etiqueta(),
            ),
            EstadoPaciente::SUSPENDIDO => sprintf(
                'Paciente suspendido. Estado anterior: %s.',
                $estadoAnterior->etiqueta(),
            ),
            EstadoPaciente::ACTIVO => sprintf(
                'Paciente reactivado. Estado anterior: %s.',
                $estadoAnterior->etiqueta(),
            ),
        };

        $entrada = new BitacoraOperativa();
        $entrada->setPaciente($paciente)
            ->setTipo(TipoBitacora::NOVEDAD)
            ->setDescripcion($descripcion)
            ->setCreadoPor($usuario);
        $this->em->persist($entrada);

        $this->em->flush();

        if ($nuevoEstado !== EstadoPaciente::ACTIVO) {
            $this->cancelarTurnosFuturos($paciente);
        }

        $this->bus->dispatch(new PacienteEstadoCambioMessage(
            pacienteId:     (string) $paciente->getId(),
            pacienteNombre: $paciente->getNombreCompleto(),
            estadoAnterior: $estadoAnterior->value,
            estadoNuevo:    $nuevoEstado->value,
        ));

        return $paciente;
    }

    public function darDeBaja(Paciente $paciente, User $usuario, string $motivo): int
    {
        if ($paciente->getEstado() === EstadoPaciente::DADO_DE_BAJA) {
            throw new \LogicException('El paciente ya está dado de baja.');
        }

        $estadoAnterior = $paciente->getEstado();

        $paciente->setEstado(EstadoPaciente::DADO_DE_BAJA);
        $paciente->setFechaTermino(new \DateTime());

        // Registrar en bitácora
        $entrada = new BitacoraOperativa();
        $entrada->setPaciente($paciente);
        $entrada->setTipo(TipoBitacora::NOVEDAD);
        $entrada->setDescripcion('Paciente dado de baja. Motivo: ' . $motivo);
        $entrada->setCreadoPor($usuario);

        $this->em->persist($entrada);
        $this->em->flush();
        $turnosCancelados = $this->cancelarTurnosFuturos($paciente);

        $this->bus->dispatch(new PacienteEstadoCambioMessage(
            pacienteId:     (string) $paciente->getId(),
            pacienteNombre: $paciente->getNombreCompleto(),
            estadoAnterior: $estadoAnterior->value,
            estadoNuevo:    EstadoPaciente::DADO_DE_BAJA->value,
        ));

        return $turnosCancelados;
    }

    public function cancelarTurnosFuturos(Paciente $paciente): int
    {
        $turnos = $this->turnoRepository->findFuturosAsignadosDePaciente($paciente);

        foreach ($turnos as $turno) {
            $turno->setTrabajador(null);
            $turno->setEstado(EstadoTurno::DESCUBIERTO);
        }

        if (count($turnos) > 0) {
            $this->em->flush();

            foreach ($turnos as $turno) {
                $this->bus->dispatch(new TurnoDescubiertoMessage(
                    turnoId:        (string) $turno->getId(),
                    pacienteNombre: $paciente->getNombreCompleto(),
                    fecha:          $turno->getFecha()?->format('d/m/Y') ?? '—',
                    tipoTurno:      $turno->getTipoTurno()->etiqueta(),
                ));
            }
        }

        return count($turnos);
    }

    public function agregarBitacora(Paciente $paciente, TipoBitacora $tipo, string $descripcion, User $usuario): BitacoraOperativa
    {
        $entrada = new BitacoraOperativa();
        $entrada->setPaciente($paciente);
        $entrada->setTipo($tipo);
        $entrada->setDescripcion($descripcion);
        $entrada->setCreadoPor($usuario);

        $this->em->persist($entrada);
        $this->em->flush();

        return $entrada;
    }

    public function agregarComunicacion(Paciente $paciente, TipoComunicacion $tipo, string $descripcion, User $usuario, ?string $contacto = null): HistorialComunicacion
    {
        $historial = new HistorialComunicacion();
        $historial->setPaciente($paciente);
        $historial->setTipo($tipo);
        $historial->setDescripcion($descripcion);
        $historial->setContacto($contacto);
        $historial->setCreadoPor($usuario);

        $this->em->persist($historial);
        $this->em->flush();

        return $historial;
    }
}
