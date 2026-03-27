<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DisponibilidadTrabajador;
use App\Entity\Trabajador;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DisponibilidadTrabajador> */
class DisponibilidadTrabajadorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DisponibilidadTrabajador::class);
    }

    public function findByTrabajadorYFecha(Trabajador $trabajador, \DateTimeInterface $fecha): ?DisponibilidadTrabajador
    {
        return $this->createQueryBuilder('d')
            ->where('d.trabajador = :trabajador')
            ->andWhere('d.fecha = :fecha')
            ->setParameter('trabajador', $trabajador)
            ->setParameter('fecha', $fecha->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
