<?php

declare(strict_types=1);

namespace App\Repository\Tenant;

use App\Entity\Tenant\Trabajador;
use App\Enum\EstadoTrabajador;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Trabajador> */
class TrabajadorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trabajador::class);
    }

    /** @return Trabajador[] */
    public function findActivos(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.estado = :estado')
            ->setParameter('estado', EstadoTrabajador::ACTIVO)
            ->orderBy('t.apellidos', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')->orderBy('t.apellidos', 'ASC');
    }
}
