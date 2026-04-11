<?php

declare(strict_types=1);

namespace App\Repository\Tenant;

use App\Entity\Tenant\WebhookSuscripcion;
use App\Enum\WebhookEvento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WebhookSuscripcionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookSuscripcion::class);
    }

    /** @return WebhookSuscripcion[] */
    public function findActivasPorEvento(WebhookEvento $evento): array
    {
        $todas = $this->createQueryBuilder('s')
            ->where('s.activo = true')
            ->getQuery()
            ->getResult();

        return array_filter($todas, fn(WebhookSuscripcion $s) => $s->escuchaEvento($evento));
    }

    /** @return WebhookSuscripcion[] */
    public function findAllOrderedByNombre(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.activo', 'DESC')
            ->addOrderBy('s.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
