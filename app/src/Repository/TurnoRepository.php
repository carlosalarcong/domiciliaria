<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Paciente;
use App\Entity\Trabajador;
use App\Entity\Turno;
use App\Enum\EstadoTurno;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Turno> */
class TurnoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Turno::class);
    }

    /** @return Turno[] */
    public function findByRangoFecha(\DateTimeInterface $desde, \DateTimeInterface $hasta): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.paciente', 'p')->addSelect('p')
            ->leftJoin('t.trabajador', 'tr')->addSelect('tr')
            ->where('t.fecha BETWEEN :desde AND :hasta')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->orderBy('t.fecha', 'ASC')
            ->addOrderBy('t.horaInicio', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Turno[] */
    public function findDescubiertosProximosDias(int $dias = 1): array
    {
        $desde = new \DateTime('today');
        $hasta = new \DateTime("+{$dias} days");

        return $this->createQueryBuilder('t')
            ->where('t.estado = :estado')
            ->andWhere('t.fecha BETWEEN :desde AND :hasta')
            ->setParameter('estado', EstadoTurno::DESCUBIERTO)
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->orderBy('t.fecha', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Turno[] */
    public function findByTrabajadorYFecha(Trabajador $trabajador, \DateTimeInterface $fecha): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.trabajador = :trabajador')
            ->andWhere('t.fecha = :fecha')
            ->setParameter('trabajador', $trabajador)
            ->setParameter('fecha', $fecha->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    /** @return Turno[] */
    public function findByTrabajadorYRango(Trabajador $trabajador, \DateTimeInterface $desde, \DateTimeInterface $hasta): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.paciente', 'p')->addSelect('p')
            ->where('t.trabajador = :trabajador')
            ->andWhere('t.fecha BETWEEN :desde AND :hasta')
            ->setParameter('trabajador', $trabajador)
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->orderBy('t.fecha', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Turno[] */
    public function findByPacienteYSemana(Paciente $paciente, \DateTimeInterface $inicioSemana): array
    {
        $finSemana = (clone $inicioSemana)->modify('+6 days');

        return $this->createQueryBuilder('t')
            ->where('t.paciente = :paciente')
            ->andWhere('t.fecha BETWEEN :desde AND :hasta')
            ->setParameter('paciente', $paciente)
            ->setParameter('desde', $inicioSemana)
            ->setParameter('hasta', $finSemana)
            ->orderBy('t.fecha', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Para FullCalendar: todos los turnos en rango como array serializable.
     */
    public function findEventosCalendario(\DateTimeInterface $desde, \DateTimeInterface $hasta): array
    {
        $turnos = $this->findByRangoFecha($desde, $hasta);
        $eventos = [];

        foreach ($turnos as $turno) {
            $fechaStr = $turno->getFecha()->format('Y-m-d');
            $inicio   = $turno->getHoraInicio()?->format('H:i') ?? '00:00';
            $termino  = $turno->getHoraTermino()?->format('H:i') ?? '23:59';

            $eventos[] = [
                'id'              => (string) $turno->getId(),
                'title'           => $turno->getTituloCalendario(),
                'start'           => "{$fechaStr}T{$inicio}",
                'end'             => "{$fechaStr}T{$termino}",
                'color'           => $turno->getEstado()->colorCalendario(),
                'extendedProps'   => [
                    'estado'      => $turno->getEstado()->value,
                    'tipoTurno'   => $turno->getTipoTurno()->etiqueta(),
                    'paciente'    => $turno->getPaciente()?->getNombreCompleto(),
                    'trabajador'  => $turno->getTrabajador()?->getNombreCompleto() ?? 'Sin asignar',
                    'esReemplazo' => $turno->isEsReemplazo(),
                ],
            ];
        }

        return $eventos;
    }
}
