<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiquidacionMensual;
use App\Entity\Trabajador;
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
