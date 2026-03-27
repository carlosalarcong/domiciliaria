<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Mandante;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mandante>
 */
class MandanteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mandante::class);
    }

    /** @return Mandante[] */
    public function findActivos(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.activo = true')
            ->orderBy('m.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.nombre', 'ASC');
    }
}
