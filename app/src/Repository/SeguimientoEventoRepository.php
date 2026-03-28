<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\SeguimientoEvento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SeguimientoEvento> */
class SeguimientoEventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeguimientoEvento::class);
    }
}
