<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\LiquidacionMensual;
use App\Entity\Tenant\Trabajador;
use App\Enum\EstadoLiquidacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LiquidacionMensual> */
class LiquidacionMensualRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiquidacionMensual::class);
    }

    public function findQueryBuilder(?int $anio = null, ?EstadoLiquidacion $estado = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.trabajador', 't')->addSelect('t')
            ->orderBy('l.anio', 'DESC')
            ->addOrderBy('l.mes', 'DESC');

        if ($anio !== null) {
            $qb->andWhere('l.anio = :anio')->setParameter('anio', $anio);
        }
        if ($estado !== null) {
            $qb->andWhere('l.estado = :estado')->setParameter('estado', $estado);
        }

        return $qb;
    }

    public function findByTrabajador(Trabajador $trabajador): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.trabajador = :trabajador')
            ->setParameter('trabajador', $trabajador)
            ->orderBy('l.anio', 'DESC')
            ->addOrderBy('l.mes', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByTrabajadorYPeriodo(Trabajador $trabajador, int $anio, int $mes): ?LiquidacionMensual
    {
        return $this->findOneBy(['trabajador' => $trabajador, 'anio' => $anio, 'mes' => $mes]);
    }

    /** @return array<int, array{id: string, nombres: string, apellidos: string, total_liquidaciones: int, suma_total: float, estado: EstadoLiquidacion}> */
    public function reportePorTrabajador(int $anio): array
    {
        return $this->createQueryBuilder('l')
            ->select('t.id', 't.nombres', 't.apellidos', 'COUNT(l.id) as total_liquidaciones',
                     'SUM(l.montoTotal) as suma_total', 'l.estado')
            ->leftJoin('l.trabajador', 't')
            ->where('l.anio = :anio')
            ->andWhere('l.estado != :borrador')
            ->setParameter('anio', $anio)
            ->setParameter('borrador', EstadoLiquidacion::BORRADOR)
            ->groupBy('t.id, t.nombres, t.apellidos, l.estado')
            ->orderBy('t.apellidos', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, array{mes: int, suma_total: string}> */
    public function reporteFlujoEgresos(int $anio): array
    {
        return $this->createQueryBuilder('l')
            ->select('l.mes', 'SUM(l.montoTotal) as suma_total')
            ->where('l.anio = :anio')
            ->andWhere('l.estado = :pagada')
            ->setParameter('anio', $anio)
            ->setParameter('pagada', EstadoLiquidacion::PAGADA)
            ->groupBy('l.mes')
            ->orderBy('l.mes', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumMontosByEstado(): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.estado, SUM(l.montoTotal) as total')
            ->groupBy('l.estado')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['estado']->value] = (float) $row['total'];
        }

        return $result;
    }
}
