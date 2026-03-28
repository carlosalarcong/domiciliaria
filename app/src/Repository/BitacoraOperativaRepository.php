<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\BitacoraOperativa;
use App\Entity\Tenant\Paciente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BitacoraOperativa> */
class BitacoraOperativaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BitacoraOperativa::class);
    }

    /** @return BitacoraOperativa[] */
    public function findByPacienteRecientes(Paciente $paciente, int $limit = 20): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.paciente = :paciente')
            ->setParameter('paciente', $paciente)
            ->orderBy('b.fecha', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
