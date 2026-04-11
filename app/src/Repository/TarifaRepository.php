<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\Mandante;
use App\Entity\Tenant\Tarifa;
use App\Enum\TipoConcepto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Tarifa>
 */
class TarifaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tarifa::class);
    }

    /**
     * Busca la tarifa vigente más específica para un concepto y fecha dados.
     *
     * Prioridad:
     *  1. Tarifa específica del mandante vigente en esa fecha
     *  2. Tarifa general (mandante = null) vigente en esa fecha
     *
     * Si no existe ninguna, retorna null.
     */
    public function findTarifaVigente(
        TipoConcepto $concepto,
        \DateTimeInterface $fecha,
        ?Mandante $mandante = null,
    ): ?Tarifa {
        $fechaStr = $fecha instanceof \DateTimeImmutable
            ? $fecha->format('Y-m-d')
            : \DateTimeImmutable::createFromInterface($fecha)->format('Y-m-d');

        $base = $this->createQueryBuilder('t')
            ->where('t.tipoConcepto = :concepto')
            ->andWhere('t.activa = true')
            ->andWhere('t.vigenciaDesde <= :fecha')
            ->andWhere('t.vigenciaHasta IS NULL OR t.vigenciaHasta >= :fecha')
            ->setParameter('concepto', $concepto)
            ->setParameter('fecha', $fechaStr);

        // 1. Intentar tarifa específica del mandante
        if ($mandante !== null) {
            $tarifa = (clone $base)
                ->andWhere('t.mandante = :mandante')
                ->setParameter('mandante', $mandante)
                ->orderBy('t.vigenciaDesde', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($tarifa !== null) {
                return $tarifa;
            }
        }

        // 2. Tarifa general
        return (clone $base)
            ->andWhere('t.mandante IS NULL')
            ->orderBy('t.vigenciaDesde', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Busca si ya existe una tarifa activa solapada para el mismo concepto y mandante.
     * Permite excluir una tarifa específica (para edición).
     */
    public function findSolapada(
        TipoConcepto $concepto,
        \DateTimeImmutable $vigenciaDesde,
        ?\DateTimeImmutable $vigenciaHasta,
        ?Mandante $mandante = null,
        ?Uuid $excludeId = null,
    ): ?Tarifa {
        $qb = $this->createQueryBuilder('t')
            ->where('t.tipoConcepto = :concepto')
            ->andWhere('t.activa = true')
            ->setParameter('concepto', $concepto);

        if ($mandante !== null) {
            $qb->andWhere('t.mandante = :mandante')->setParameter('mandante', $mandante);
        } else {
            $qb->andWhere('t.mandante IS NULL');
        }

        $qb->andWhere(
            '(:desde <= COALESCE(t.vigenciaHasta, :infinito)) AND (COALESCE(:hasta, :infinito) >= t.vigenciaDesde)'
        )
            ->setParameter('desde', $vigenciaDesde->format('Y-m-d'))
            ->setParameter('hasta', $vigenciaHasta?->format('Y-m-d'))
            ->setParameter('infinito', '9999-12-31');

        if ($excludeId !== null) {
            $qb->andWhere('t.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    /**
     * Retorna todas las tarifas activas agrupables para el listado.
     *
     * @return Tarifa[]
     */
    public function findActivas(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.mandante', 'm')
            ->addSelect('m')
            ->where('t.activa = true')
            ->orderBy('m.nombre', 'ASC')
            ->addOrderBy('t.tipoConcepto', 'ASC')
            ->addOrderBy('t.vigenciaDesde', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tarifa[]
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.mandante', 'm')
            ->addSelect('m')
            ->orderBy('t.activa', 'DESC')
            ->addOrderBy('m.nombre', 'ASC')
            ->addOrderBy('t.tipoConcepto', 'ASC')
            ->addOrderBy('t.vigenciaDesde', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
