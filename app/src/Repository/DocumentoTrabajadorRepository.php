<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\DocumentoTrabajador;
use App\Entity\Tenant\Trabajador;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DocumentoTrabajador> */
class DocumentoTrabajadorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentoTrabajador::class);
    }

    /** @return DocumentoTrabajador[] */
    public function findByTrabajador(Trabajador $trabajador): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.trabajador = :trabajador')
            ->setParameter('trabajador', $trabajador)
            ->orderBy('d.creadoEn', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
