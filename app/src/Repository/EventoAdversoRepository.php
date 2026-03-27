<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EventoAdverso;
use App\Entity\Paciente;
use App\Enum\EstadoEvento;
use App\Enum\GravedadEvento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EventoAdverso> */
class EventoAdversoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventoAdverso::class);
    }

    public function findQueryBuilder(?EstadoEvento $estado = null, ?GravedadEvento $gravedad = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.paciente', 'p')->addSelect('p')
            ->leftJoin('e.trabajador', 't')->addSelect('t')
            ->orderBy('e.fechaEvento', 'DESC')
            ->addOrderBy('e.creadoEn', 'DESC');

        if ($estado !== null) {
            $qb->andWhere('e.estado = :estado')->setParameter('estado', $estado);
        }
        if ($gravedad !== null) {
            $qb->andWhere('e.gravedad = :gravedad')->setParameter('gravedad', $gravedad);
        }

        return $qb;
    }

    /** @return EventoAdverso[] */
    public function findByPaciente(Paciente $paciente): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.paciente = :paciente')
            ->setParameter('paciente', $paciente)
            ->orderBy('e.fechaEvento', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return EventoAdverso[] */
    public function findAbiertosGraves(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.paciente', 'p')->addSelect('p')
            ->where('e.estado != :cerrado')
            ->andWhere('e.gravedad IN (:graves)')
            ->setParameter('cerrado', EstadoEvento::CERRADO)
            ->setParameter('graves', [GravedadEvento::GRAVE, GravedadEvento::CRITICO])
            ->orderBy('e.fechaEvento', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByEstado(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.estado, COUNT(e.id) as total')
            ->groupBy('e.estado')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['estado']->value] = (int) $row['total'];
        }

        return $result;
    }
}
