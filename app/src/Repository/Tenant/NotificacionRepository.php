<?php

declare(strict_types=1);

namespace App\Repository\Tenant;

use App\Entity\Tenant\Notificacion;
use App\Entity\Tenant\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notificacion> */
class NotificacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notificacion::class);
    }

    /** @return Notificacion[] */
    public function findNoLeidasByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.destinatario = :user')
            ->andWhere('n.leidaEn IS NULL')
            ->setParameter('user', $user)
            ->orderBy('n.creadoEn', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Notificacion[] */
    public function findRecientesByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.destinatario = :user')
            ->setParameter('user', $user)
            ->orderBy('n.creadoEn', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countNoLeidasByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.destinatario = :user')
            ->andWhere('n.leidaEn IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function marcarTodasLeidasByUser(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.leidaEn', ':ahora')
            ->where('n.destinatario = :user')
            ->andWhere('n.leidaEn IS NULL')
            ->setParameter('ahora', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
