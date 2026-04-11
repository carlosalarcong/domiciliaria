<?php

declare(strict_types=1);

namespace App\Repository\Tenant;

use App\Entity\Tenant\ApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ApiToken> */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    public function findByPlainToken(string $plainToken): ?ApiToken
    {
        return $this->findOneBy(['tokenHash' => ApiToken::hashToken($plainToken)]);
    }
}
