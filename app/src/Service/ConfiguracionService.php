<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\ConfiguracionClinica;
use App\Repository\ConfiguracionClinicaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ConfiguracionService
{
    private ?ConfiguracionClinica $cache = null;

    public function __construct(
        private readonly ConfiguracionClinicaRepository $repo,
        #[Autowire(service: 'doctrine.orm.tenant_entity_manager')]
        private readonly EntityManagerInterface $em,
    ) {}

    public function get(): ConfiguracionClinica
    {
        if ($this->cache === null) {
            $this->cache = $this->repo->getOrCreate($this->em);
        }

        return $this->cache;
    }
}
