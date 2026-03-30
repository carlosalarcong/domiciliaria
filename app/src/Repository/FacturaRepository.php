<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\Factura;
use App\Entity\Tenant\Mandante;
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

    public function findVencidas(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.mandante', 'm')->addSelect('m')
            ->where('f.estado = :emitida')
            ->andWhere('f.fechaVencimiento < :hoy')
            ->setParameter('emitida', EstadoFactura::EMITIDA)
            ->setParameter('hoy', new \DateTime('today'))
            ->orderBy('f.fechaVencimiento', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, array{id: string, nombre: string, total_facturas: int, suma_neto: float, suma_iva: float, suma_total: float, estado: EstadoFactura}> */
    public function reportePorMandante(int $anio): array
    {
        return $this->createQueryBuilder('f')
            ->select('m.id', 'm.nombre', 'COUNT(f.id) as total_facturas',
                     'SUM(f.montoNeto) as suma_neto', 'SUM(f.montoIva) as suma_iva',
                     'SUM(f.montoTotal) as suma_total', 'f.estado')
            ->leftJoin('f.mandante', 'm')
            ->where('f.anio = :anio')
            ->andWhere('f.estado != :borrador')
            ->setParameter('anio', $anio)
            ->setParameter('borrador', EstadoFactura::BORRADOR)
            ->groupBy('m.id, m.nombre, f.estado')
            ->orderBy('m.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, array{mes: int, suma_total: string}> */
    public function reporteFlujoIngresos(int $anio): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.mes', 'SUM(f.montoTotal) as suma_total')
            ->where('f.anio = :anio')
            ->andWhere('f.estado = :pagada')
            ->setParameter('anio', $anio)
            ->setParameter('pagada', EstadoFactura::PAGADA)
            ->groupBy('f.mes')
            ->orderBy('f.mes', 'ASC')
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
