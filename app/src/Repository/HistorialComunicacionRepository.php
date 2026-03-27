<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\HistorialComunicacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HistorialComunicacion> */
class HistorialComunicacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistorialComunicacion::class);
    }
}
