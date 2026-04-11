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
    public function findParcialesVencidos(int $horas = 26): array
    {
        $limite = new \DateTime("-{$horas} hours");

        return $this->createQueryBuilder('t')
            ->where('t.estado = :estado')
            ->andWhere('t.registroInicio <= :limite')
            ->setParameter('estado', EstadoTurno::PARCIAL)
            ->setParameter('limite', $limite)
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

    /**
     * Retorna turnos futuros CUBIERTOS o PARCIALES asignados a un trabajador.
     *
     * @return Turno[]
     */
    public function findFuturosCubiertosDesTrabajador(Trabajador $trabajador): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.trabajador = :trabajador')
            ->andWhere('t.fecha >= :hoy')
            ->andWhere('(t.estado = :cubierto) OR (t.estado = :parcial AND t.registroInicio IS NULL)')
            ->setParameter('trabajador', $trabajador)
            ->setParameter('hoy', new \DateTime('today'))
            ->setParameter('cubierto', EstadoTurno::CUBIERTO)
            ->setParameter('parcial', EstadoTurno::PARCIAL)
            ->getQuery()
            ->getResult();
    }

    /** @return Turno[] */
    public function findFuturosAsignadosDePaciente(Paciente $paciente): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.paciente = :paciente')
            ->andWhere('t.fecha >= :hoy')
            ->andWhere('(t.estado = :cubierto) OR (t.estado = :parcial AND t.registroInicio IS NULL)')
            ->setParameter('paciente', $paciente)
            ->setParameter('hoy', new \DateTime('today'))
            ->setParameter('cubierto', EstadoTurno::CUBIERTO)
            ->setParameter('parcial', EstadoTurno::PARCIAL)
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
    public function findEventosCalendario(
        \DateTimeInterface $desde,
        \DateTimeInterface $hasta,
        ?string $trabajadorId = null,
        ?string $estado = null,
        ?string $tipo = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.paciente', 'p')->addSelect('p')
            ->leftJoin('t.trabajador', 'tr')->addSelect('tr')
            ->where('t.fecha BETWEEN :desde AND :hasta')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta);

        if ($trabajadorId !== null) {
            $qb->andWhere('tr.id = :trabajadorId')
                ->setParameter('trabajadorId', $trabajadorId);
        }
        if ($estado !== null && EstadoTurno::tryFrom($estado) !== null) {
            $qb->andWhere('t.estado = :estado')
                ->setParameter('estado', EstadoTurno::from($estado));
        }
        if ($tipo !== null && TipoTurno::tryFrom($tipo) !== null) {
            $qb->andWhere('t.tipoTurno = :tipo')
                ->setParameter('tipo', TipoTurno::from($tipo));
        }

        $turnos  = $qb->orderBy('t.fecha', 'ASC')->getQuery()->getResult();
        $eventos = [];

        foreach ($turnos as $turno) {
            $fechaStr = $turno->getFecha()->format('Y-m-d');
            $inicio   = $turno->getHoraInicio()?->format('H:i') ?? '00:00';
            $termino  = $turno->getHoraTermino()?->format('H:i') ?? '23:59';
            $trabajador       = $turno->getTrabajador();
            $trabajadorNombre = $trabajador?->getNombreCompleto() ?? 'Sin asignar';
            $iniciales        = $trabajador
                ? mb_strtoupper(mb_substr($trabajador->getNombres() ?? '', 0, 1)
                    . mb_substr($trabajador->getApellidos() ?? '', 0, 1))
                : '??';

            $eventos[] = [
                'id'            => (string) $turno->getId(),
                'title'         => $turno->getTituloCalendario(),
                'start'         => "{$fechaStr}T{$inicio}",
                'end'           => "{$fechaStr}T{$termino}",
                'color'         => $turno->getEstado()->colorCalendario(),
                'extendedProps' => [
                    'estado' => $turno->getEstado()->value,
                    'tipoTurno' => $turno->getTipoTurno()->value,
                    'tipoTurnoLabel' => $turno->getTipoTurno()->etiqueta(),
                    'paciente' => $turno->getPaciente()?->getNombreCompleto(),
                    'trabajador' => $trabajadorNombre,
                    'trabajadorId' => $trabajador ? (string) $trabajador->getId() : null,
                    'iniciales' => $iniciales,
                    'esReemplazo' => $turno->isEsReemplazo(),
                    'horaInicio' => $inicio,
                ],
            ];
        }

        return $eventos;
    }

    public function countCompletadosMesActual(): int
    {
        $desde = new \DateTime('first day of this month');
        $hasta = new \DateTime('last day of this month');

        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.estado = :estado')
            ->andWhere('t.fecha BETWEEN :desde AND :hasta')
            ->setParameter('estado', EstadoTurno::COMPLETADO)
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta)
            ->getQuery()
            ->getSingleScalarResult();
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
            $resumen[$key]['total_horas'] += $this->calcularHorasEfectivasTurno($turno);
        }

        // Ordenar descendente por mes
        krsort($resumen);

        return array_values($resumen);
    }

    private function calcularHorasEfectivasTurno(Turno $turno): float
    {
        $inicio  = $turno->getRegistroInicio();
        $termino = $turno->getRegistroTermino();

        if ($inicio !== null && $termino !== null) {
            $minutos = ($termino->getTimestamp() - $inicio->getTimestamp()) / 60;
            if ($minutos < 0) {
                $minutos += 1440; // turno nocturno
            }

            return round($minutos / 60, 2);
        }

        return (float) $turno->getTipoTurno()->duracionHoras();
    }
}
