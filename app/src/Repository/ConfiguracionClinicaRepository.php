<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\ConfiguracionClinica;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ConfiguracionClinica> */
class ConfiguracionClinicaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfiguracionClinica::class);
    }

    public function getOrCreate(EntityManagerInterface $em): ConfiguracionClinica
    {
        $config = $this->findOneBy([]);

        if ($config === null) {
            $config = new ConfiguracionClinica();
            $em->persist($config);
            $em->flush();
        }

        return $config;
    }
}
