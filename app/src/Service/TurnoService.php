<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Entity\Tenant\Turno;
use App\Entity\Tenant\User;
use App\Enum\EstadoTrabajador;
use App\Enum\EstadoTurno;
use App\Enum\MotivoReemplazo;
use App\Message\TurnoDescubiertoMessage;
use App\Repository\DisponibilidadTrabajadorRepository;
use App\Repository\TurnoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TurnoService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TurnoRepository $turnoRepository,
        private readonly DisponibilidadTrabajadorRepository $disponibilidadRepository,
        private readonly MessageBusInterface $bus,
    ) {}

    public function crear(Turno $turno, User $creadoPor): Turno
    {
        $turno->setCreadoPor($creadoPor);

        // Si tiene trabajador asignado, queda CUBIERTO; si no, DESCUBIERTO
        if ($turno->getTrabajador() !== null) {
            $trabajador = $turno->getTrabajador();
            if ($trabajador->getEstado() !== EstadoTrabajador::ACTIVO) {
                throw new \DomainException(sprintf(
                    'No se puede asignar el turno: el trabajador %s no está activo (estado: %s).',
                    $trabajador->getNombreCompleto(),
                    $trabajador->getEstado()->etiqueta(),
                ));
            }
            $this->verificarDisponibilidad($turno->getTrabajador(), $turno->getFecha(), $turno->getHoraInicio(), $turno->getHoraTermino());
            $turno->setEstado(EstadoTurno::CUBIERTO);
        } else {
            $turno->setEstado(EstadoTurno::DESCUBIERTO);
            $this->despacharAlertaDescubierto($turno);
        }

        $this->em->persist($turno);
        $this->em->flush();

        return $turno;
    }

    /**
     * Recalcula y aplica el estado correcto del turno según si tiene o no trabajador.
     *
     * @throws \DomainException si el trabajador no está activo o tiene conflicto
     */
    public function actualizarEstadoSegunTrabajador(Turno $turno): void
    {
        if ($turno->getTrabajador() !== null) {
            $trabajador = $turno->getTrabajador();

            if ($trabajador->getEstado() !== EstadoTrabajador::ACTIVO) {
                throw new \DomainException(sprintf(
                    'No se puede asignar el turno: el trabajador %s no está activo (estado: %s).',
                    $trabajador->getNombreCompleto(),
                    $trabajador->getEstado()->etiqueta(),
                ));
            }

            $this->verificarDisponibilidad(
                $trabajador,
                $turno->getFecha(),
                $turno->getHoraInicio(),
                $turno->getHoraTermino(),
            );

            if ($turno->getEstado() === EstadoTurno::DESCUBIERTO) {
                $turno->setEstado(EstadoTurno::CUBIERTO);
            }
        } else {
            if (in_array($turno->getEstado(), [EstadoTurno::CUBIERTO, EstadoTurno::PARCIAL], true)) {
                $turno->setEstado(EstadoTurno::DESCUBIERTO);
            }
        }
    }

    /**
     * Verifica que el trabajador no tenga conflicto de horario.
     *
     * @throws \DomainException si hay conflicto
     */
    public function verificarDisponibilidad(
        Trabajador $trabajador,
        \DateTimeInterface $fecha,
        \DateTimeInterface $horaInicio,
        \DateTimeInterface $horaTermino,
    ): void {
        $disponibilidad = $this->disponibilidadRepository->findByTrabajadorYFecha($trabajador, $fecha);

        if ($disponibilidad !== null && !$disponibilidad->isDisponible()) {
            throw new \DomainException(sprintf(
                'El trabajador %s tiene registrada no disponibilidad el %s. Motivo: %s',
                $trabajador->getNombreCompleto(),
                $fecha->format('d/m/Y'),
                $disponibilidad->getObservacion() ?? 'sin especificar',
            ));
        }

        if ($disponibilidad !== null
            && $disponibilidad->isDisponible()
            && $disponibilidad->getHoraDesde() !== null
            && $disponibilidad->getHoraHasta() !== null
        ) {
            if ($horaInicio < $disponibilidad->getHoraDesde()
                || $horaTermino > $disponibilidad->getHoraHasta()
            ) {
                throw new \DomainException(sprintf(
                    'El turno (%s–%s) está fuera del horario de disponibilidad del trabajador %s el %s (%s–%s).',
                    $horaInicio->format('H:i'),
                    $horaTermino->format('H:i'),
                    $trabajador->getNombreCompleto(),
                    $fecha->format('d/m/Y'),
                    $disponibilidad->getHoraDesde()->format('H:i'),
                    $disponibilidad->getHoraHasta()->format('H:i'),
                ));
            }
        }

        $turnosExistentes = $this->turnoRepository->findByTrabajadorYFecha($trabajador, $fecha);

        foreach ($turnosExistentes as $existente) {
            if ($existente->getEstado() === EstadoTurno::DESCUBIERTO) {
                continue;
            }

            $inicioExistente  = $existente->getHoraInicio();
            $terminoExistente = $existente->getHoraTermino();

            // Detecta solapamiento
            if ($horaInicio < $terminoExistente && $horaTermino > $inicioExistente) {
                throw new \DomainException(sprintf(
                    'El trabajador %s ya tiene un turno asignado el %s entre %s y %s.',
                    $trabajador->getNombreCompleto(),
                    $fecha->format('d/m/Y'),
                    $inicioExistente->format('H:i'),
                    $terminoExistente->format('H:i'),
                ));
            }
        }
    }

    public function asignarReemplazo(Turno $turno, Trabajador $reemplazo, MotivoReemplazo $motivo): Turno
    {
        if (!in_array($turno->getEstado(), [EstadoTurno::DESCUBIERTO, EstadoTurno::CUBIERTO], true)) {
            throw new \DomainException(sprintf(
                'No se puede asignar reemplazo a un turno en estado "%s". Solo se permite en turnos Descubiertos o Cubiertos.',
                $turno->getEstado()->etiqueta(),
            ));
        }

        if ($reemplazo->getEstado() !== EstadoTrabajador::ACTIVO) {
            throw new \DomainException(sprintf(
                'No se puede asignar el turno: el trabajador %s no está activo (estado: %s).',
                $reemplazo->getNombreCompleto(),
                $reemplazo->getEstado()->etiqueta(),
            ));
        }

        $this->verificarDisponibilidad(
            $reemplazo,
            $turno->getFecha(),
            $turno->getHoraInicio(),
            $turno->getHoraTermino(),
        );

        $turno->setTrabajador($reemplazo);
        $turno->setEsReemplazo(true);
        $turno->setMotivoReemplazo($motivo);
        $turno->setEstado(EstadoTurno::CUBIERTO);

        $this->em->flush();

        return $turno;
    }

    public function registrarAsistencia(Turno $turno, string $tipo): Turno
    {
        if ($tipo === 'inicio') {
            $turno->setRegistroInicio(new \DateTime());
            $turno->setEstado(EstadoTurno::PARCIAL);
        } elseif ($tipo === 'termino') {
            if ($turno->getRegistroInicio() === null) {
                throw new \LogicException('No se puede registrar el término sin haber registrado el inicio.');
            }
            $turno->setRegistroTermino(new \DateTime());
            $turno->setEstado(EstadoTurno::COMPLETADO);
        }

        $this->em->flush();

        return $turno;
    }

    public function calcularEstadoCobertura(Paciente $paciente, \DateTimeInterface $inicioSemana): array
    {
        $turnos = $this->turnoRepository->findByPacienteYSemana($paciente, $inicioSemana);

        $resumen = [
            'total'       => count($turnos),
            'cubiertos'   => 0,
            'parciales'   => 0,
            'descubiertos' => 0,
            'completados' => 0,
            'porcentaje'  => 0,
        ];

        foreach ($turnos as $turno) {
            match ($turno->getEstado()) {
                EstadoTurno::CUBIERTO    => $resumen['cubiertos']++,
                EstadoTurno::PARCIAL     => $resumen['parciales']++,
                EstadoTurno::DESCUBIERTO => $resumen['descubiertos']++,
                EstadoTurno::COMPLETADO  => $resumen['completados']++,
            };
        }

        if ($resumen['total'] > 0) {
            $resumen['porcentaje'] = round(
                (($resumen['cubiertos'] + $resumen['completados']) / $resumen['total']) * 100
            );
        }

        return $resumen;
    }

    private function despacharAlertaDescubierto(Turno $turno): void
    {
        $this->bus->dispatch(new TurnoDescubiertoMessage(
            turnoId:        (string) $turno->getId(),
            pacienteNombre: $turno->getPaciente()?->getNombreCompleto() ?? 'Desconocido',
            fecha:          $turno->getFecha()?->format('d/m/Y') ?? '—',
            tipoTurno:      $turno->getTipoTurno()->etiqueta(),
        ));
    }
}
