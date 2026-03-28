<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\ItemLiquidacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ItemLiquidacion> */
class ItemLiquidacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemLiquidacion::class);
    }
}
