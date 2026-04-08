<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\Mandante;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Entity\Tenant\Turno;
use App\Enum\EstadoTurno;
use App\Enum\TipoTurno;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Turno> */
class TurnoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Turno::class);
    }

    /** @return array<string, int> estado => count para los turnos de hoy */
    public function countHoyByEstado(): array
    {
        $hoy  = new \DateTime('today');
        $rows = $this->createQueryBuilder('t')
            ->select('t.estado, COUNT(t.id) as total')
            ->where('t.fecha = :hoy')
            ->setParameter('hoy', $hoy)
            ->groupBy('t.estado')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['estado']->value] = (int) $row['total'];
        }
        return $result;
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
    public function findByMandanteYRango(Mandante $mandante, \DateTimeInterface $desde, \DateTimeInterface $hasta): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.paciente', 'p')->addSelect('p')
            ->where('p.mandante = :mandante')
            ->andWhere('t.fecha BETWEEN :desde AND :hasta')
            ->setParameter('mandante', $mandante)
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
     * Reporte por paciente: agrupa turnos de un año por paciente y estado.
     * @return array<int, array{paciente_id: string, nombres: string, apellidos: string, estado: EstadoTurno, tipo_turno: TipoTurno, total: int}>
     */
    public function reportePorPaciente(int $anio): array
    {
        $desde = new \DateTime("{$anio}-01-01");
        $hasta = new \DateTime("{$anio}-12-31");

        return $this->createQueryBuilder('t')
            ->select(
                'IDENTITY(t.paciente) as paciente_id',
                'p.nombres',
                'p.apellidos',
                't.estado',
                't.tipoTurno as tipo_turno',
                'COUNT(t.id) as total',
            )
            ->leftJoin('t.paciente', 'p')
            ->where('t.fecha BETWEEN :desde AND :hasta')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->groupBy('t.paciente, p.nombres, p.apellidos, t.estado, t.tipoTurno')
            ->orderBy('p.apellidos', 'ASC')
            ->addOrderBy('p.nombres', 'ASC')
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

    /**
     * Historial paginado de turnos de un trabajador, con filtro opcional por año y mes.
     *
     * @return Turno[]
     */
    public function findHistorialByTrabajador(
        Trabajador $trabajador,
        ?int $anio = null,
        ?int $mes  = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.paciente', 'p')
            ->addSelect('p')
            ->where('t.trabajador = :trabajador')
            ->setParameter('trabajador', $trabajador)
            ->orderBy('t.fecha', 'DESC')
            ->addOrderBy('t.horaInicio', 'DESC');

        if ($anio !== null) {
            $qb->andWhere('t.fecha BETWEEN :desde AND :hasta')
               ->setParameter('desde', new \DateTime("{$anio}-" . ($mes ?? '01') . "-01"))
               ->setParameter('hasta', $mes !== null
                   ? (new \DateTime("{$anio}-{$mes}-01"))->modify('last day of this month')
                   : new \DateTime("{$anio}-12-31"));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Resumen de horas trabajadas por mes en un año dado, para un trabajador.
     * Devuelve array: [['anio_mes'=>'2026-03','total_turnos'=>10,'total_horas'=>120], ...]
     * ordenado descendentemente por mes.
     */
    public function resumenHorasPorMes(Trabajador $trabajador, int $anio): array
    {
        $desde = new \DateTime("{$anio}-01-01");
        $hasta = new \DateTime("{$anio}-12-31");

        /** @var Turno[] $turnos */
        $turnos = $this->createQueryBuilder('t')
            ->where('t.trabajador = :trabajador')
            ->andWhere('t.fecha BETWEEN :desde AND :hasta')
            ->andWhere('t.estado != :desc')
            ->setParameter('trabajador', $trabajador)
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->setParameter('desc', EstadoTurno::DESCUBIERTO)
            ->orderBy('t.fecha', 'DESC')
            ->getQuery()
            ->getResult();

        // Agrupar en PHP por año-mes
        $resumen = [];
        foreach ($turnos as $turno) {
            $key = $turno->getFecha()->format('Y-m');
            if (!isset($resumen[$key])) {
                $resumen[$key] = ['anio_mes' => $key, 'total_turnos' => 0, 'total_horas' => 0];
            }
            $resumen[$key]['total_turnos']++;
            $resumen[$key]['total_horas'] += $turno->getTipoTurno()->duracionHoras();
        }

        // Ordenar descendente por mes
        krsort($resumen);

        return array_values($resumen);
    }
}
