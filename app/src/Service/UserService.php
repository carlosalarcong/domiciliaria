<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function crear(User $user, string $plainPassword): User
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function actualizar(User $user, ?string $plainPassword = null): User
    {
        if ($plainPassword !== null && $plainPassword !== '') {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }

        $this->em->flush();

        return $user;
    }

    public function toggleActivo(User $user): User
    {
        $user->setActivo(!$user->isActivo());
        $this->em->flush();

        return $user;
    }

    public function findActivos(): array
    {
        return $this->userRepository->findActivos();
    }
}
