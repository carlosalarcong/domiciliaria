<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Factura;
use App\Entity\Mandante;
use App\Enum\EstadoFactura;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Factura> */
class FacturaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Factura::class);
    }

    public function findQueryBuilder(?int $anio = null, ?EstadoFactura $estado = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.mandante', 'm')->addSelect('m')
            ->orderBy('f.anio', 'DESC')
            ->addOrderBy('f.mes', 'DESC');

        if ($anio !== null) {
            $qb->andWhere('f.anio = :anio')->setParameter('anio', $anio);
        }
        if ($estado !== null) {
            $qb->andWhere('f.estado = :estado')->setParameter('estado', $estado);
        }

        return $qb;
    }

    public function findByMandante(Mandante $mandante): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.mandante = :mandante')
            ->setParameter('mandante', $mandante)
            ->orderBy('f.anio', 'DESC')
            ->addOrderBy('f.mes', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function sumMontosByEstado(): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('f.estado, SUM(f.montoTotal) as total')
            ->groupBy('f.estado')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['estado']->value] = (float) $row['total'];
        }

        return $result;
    }
}
