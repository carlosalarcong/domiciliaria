<?php

declare(strict_types=1);

namespace App\Repository\Tenant;

use App\Entity\Tenant\WebhookDelivery;
use App\Entity\Tenant\WebhookSuscripcion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class WebhookDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookDelivery::class);
    }

    public function findQueryBuilder(?WebhookSuscripcion $suscripcion, ?string $estado): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.suscripcion', 's')->addSelect('s')
            ->orderBy('d.creadoEn', 'DESC');

        if ($suscripcion) {
            $qb->andWhere('d.suscripcion = :suscripcion')
               ->setParameter('suscripcion', $suscripcion);
        }
        if ($estado) {
            $qb->andWhere('d.estado = :estado')
               ->setParameter('estado', $estado);
        }

        return $qb;
    }

    public function countByEstado(WebhookSuscripcion $suscripcion): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.estado, COUNT(d.id) as total')
            ->where('d.suscripcion = :s')
            ->setParameter('s', $suscripcion)
            ->groupBy('d.estado')
            ->getQuery()
            ->getResult();

        $result = [
            WebhookDelivery::ESTADO_PENDIENTE  => 0,
            WebhookDelivery::ESTADO_ENTREGADO  => 0,
            WebhookDelivery::ESTADO_FALLIDO    => 0,
        ];
        foreach ($rows as $row) {
            $result[$row['estado']] = (int) $row['total'];
        }
        return $result;
    }
}
