<?php

declare(strict_types=1);

namespace App\Repository\Tenant;

use App\Entity\Tenant\SincronizacionExterna;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class SincronizacionExternaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SincronizacionExterna::class);
    }

    public function findQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.activa', 'DESC')
            ->addOrderBy('s.nombre', 'ASC');
    }

    public function findActivas(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.activa = true')
            ->orderBy('s.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
