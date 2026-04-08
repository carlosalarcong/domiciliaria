<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /** @return User[] */
    public function findActivos(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.activo = true')
            ->orderBy('u.apellido', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtra en PHP porque roles es JSON y PostgreSQL no acepta LIKE sobre JSON en DQL.
     *
     * @return User[]
     */
    public function findByRol(string $rol): array
    {
        $todos = $this->findActivos();

        return array_values(array_filter($todos, fn(User $u) => in_array($rol, $u->getRoles(), true)));
    }

    /**
     * Devuelve usuarios activos que tengan al menos uno de los roles indicados.
     *
     * @param string[] $roles
     * @return User[]
     */
    public function findActivosByRoles(array $roles): array
    {
        $todos = $this->findActivos();

        return array_values(array_filter(
            $todos,
            fn(User $u) => count(array_intersect($roles, $u->getRoles())) > 0
        ));
    }
}
