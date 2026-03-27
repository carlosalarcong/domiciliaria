<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CondicionDomicilio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CondicionDomicilio> */
class CondicionDomicilioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CondicionDomicilio::class);
    }
}
