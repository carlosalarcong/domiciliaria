<?php

declare(strict_types=1);

namespace App\Repository\Tenant;

use App\Entity\Tenant\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findQueryBuilder(?string $evento, ?string $ip, ?string $email): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($evento) {
            $qb->andWhere('a.evento = :evento')->setParameter('evento', $evento);
        }
        if ($ip) {
            $qb->andWhere('a.ipAddress LIKE :ip')->setParameter('ip', '%' . $ip . '%');
        }
        if ($email) {
            $qb->andWhere('a.emailIntentado LIKE :email')->setParameter('email', '%' . $email . '%');
        }

        return $qb;
    }
}
