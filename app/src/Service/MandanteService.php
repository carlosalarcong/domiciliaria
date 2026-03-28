<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\Mandante;
use App\Repository\MandanteRepository;
use Doctrine\ORM\EntityManagerInterface;

class MandanteService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MandanteRepository $mandanteRepository,
    ) {}

    public function crear(Mandante $mandante): Mandante
    {
        $this->em->persist($mandante);
        $this->em->flush();
        return $mandante;
    }

    public function actualizar(Mandante $mandante): Mandante
    {
        $this->em->flush();
        return $mandante;
    }

    public function toggleActivo(Mandante $mandante): Mandante
    {
        $mandante->setActivo(!$mandante->isActivo());
        $this->em->flush();
        return $mandante;
    }
}
