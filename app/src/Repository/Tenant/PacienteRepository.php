<?php

declare(strict_types=1);

namespace App\Repository\Tenant;

use App\Entity\Tenant\Mandante;
use App\Entity\Tenant\Paciente;
use App\Enum\EstadoPaciente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paciente>
 */
class PacienteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paciente::class);
    }

    /** @return Paciente[] */
    public function findActivos(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.estado = :estado')
            ->setParameter('estado', EstadoPaciente::ACTIVO)
            ->orderBy('p.apellidos', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Paciente[] */
    public function findByMandante(Mandante $mandante): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.mandante = :mandante')
            ->setParameter('mandante', $mandante)
            ->orderBy('p.apellidos', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retorna pacientes cuyo próximo turno no tiene cobertura.
     * Implementación completa en Fase 3 cuando exista la entidad Turno.
     *
     * @return Paciente[]
     */
    public function findConTurnosDescubiertos(): array
    {
        // TODO Fase 3: implementar con JOIN a Turno y filtro por estado = DESCUBIERTO
        return [];
    }

    public function findQueryBuilder(?string $estado = null, ?string $mandanteId = null, ?string $tipoServicio = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.mandante', 'm')
            ->addSelect('m')
            ->orderBy('p.apellidos', 'ASC');

        if ($estado !== null && $estado !== '') {
            $qb->andWhere('p.estado = :estado')
               ->setParameter('estado', $estado);
        }

        if ($mandanteId !== null && $mandanteId !== '') {
            $qb->andWhere('m.id = :mandante')
               ->setParameter('mandante', $mandanteId);
        }

        if ($tipoServicio !== null && $tipoServicio !== '') {
            $qb->andWhere('p.tipoServicio = :tipo')
               ->setParameter('tipo', $tipoServicio);
        }

        return $qb;
    }
}
