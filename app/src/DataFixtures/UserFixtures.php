<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $usuarios = [
            [
                'email' => 'admin@domiciliaria.cl',
                'nombre' => 'Carlos',
                'apellido' => 'Administrador',
                'roles' => ['ROLE_ADMIN'],
                'password' => 'admin1234',
            ],
            [
                'email' => 'coordinador@domiciliaria.cl',
                'nombre' => 'María',
                'apellido' => 'González',
                'roles' => ['ROLE_COORDINADOR'],
                'password' => 'coord1234',
            ],
            [
                'email' => 'enfermera@domiciliaria.cl',
                'nombre' => 'Ana',
                'apellido' => 'Martínez',
                'roles' => ['ROLE_ENFERMERA'],
                'password' => 'enf1234!',
            ],
            [
                'email' => 'tens@domiciliaria.cl',
                'nombre' => 'Pedro',
                'apellido' => 'López',
                'roles' => ['ROLE_TENS'],
                'password' => 'tens1234',
            ],
            [
                'email' => 'visualizador@domiciliaria.cl',
                'nombre' => 'Lucía',
                'apellido' => 'Rodríguez',
                'roles' => ['ROLE_VISUALIZADOR'],
                'password' => 'vis12345',
            ],
        ];

        foreach ($usuarios as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setNombre($data['nombre']);
            $user->setApellido($data['apellido']);
            $user->setRoles($data['roles']);
            $user->setActivo(true);

            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            $manager->persist($user);
        }

        $manager->flush();
    }
}
