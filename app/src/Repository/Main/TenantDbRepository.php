<?php

declare(strict_types=1);

namespace App\Repository\Main;

use App\Entity\Main\TenantDb;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TenantDb>
 */
class TenantDbRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantDb::class);
    }

    public function findBySlug(string $slug): ?TenantDb
    {
        return $this->findOneBy(['slug' => $slug, 'isActive' => true]);
    }

    /** @return TenantDb[] */
    public function findAllActivos(): array
    {
        return $this->findBy(['isActive' => true], ['name' => 'ASC']);
    }
}
