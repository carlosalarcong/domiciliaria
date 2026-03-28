<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Tenant\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[TenantFixture]
class UserFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int { return 1; }

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $db = $manager->getConnection()->getDatabase();

        $usuarios = match (true) {
            str_contains($db, 'norte') => [
                ['email' => 'admin@clinica-norte.cl',        'nombre' => 'Roberto',   'apellido' => 'Saavedra',   'roles' => ['ROLE_ADMIN'],        'password' => 'admin1234'],
                ['email' => 'coordinador@clinica-norte.cl',  'nombre' => 'Valentina', 'apellido' => 'Herrera',    'roles' => ['ROLE_COORDINADOR'],  'password' => 'coord1234'],
                ['email' => 'enfermera@clinica-norte.cl',    'nombre' => 'Daniela',   'apellido' => 'Castillo',   'roles' => ['ROLE_ENFERMERA'],    'password' => 'enf1234!'],
                ['email' => 'tens@clinica-norte.cl',         'nombre' => 'Sebastián', 'apellido' => 'Morales',    'roles' => ['ROLE_TENS'],         'password' => 'tens1234'],
                ['email' => 'visualizador@clinica-norte.cl', 'nombre' => 'Javiera',   'apellido' => 'Fuentes',    'roles' => ['ROLE_VISUALIZADOR'], 'password' => 'vis12345'],
            ],
            default => [ // clinica_demo y cualquier otra
                ['email' => 'admin@clinica-demo.cl',         'nombre' => 'Carlos',    'apellido' => 'Administrador', 'roles' => ['ROLE_ADMIN'],        'password' => 'admin1234'],
                ['email' => 'coordinador@clinica-demo.cl',   'nombre' => 'María',     'apellido' => 'González',      'roles' => ['ROLE_COORDINADOR'],  'password' => 'coord1234'],
                ['email' => 'enfermera@clinica-demo.cl',     'nombre' => 'Ana',       'apellido' => 'Martínez',      'roles' => ['ROLE_ENFERMERA'],    'password' => 'enf1234!'],
                ['email' => 'tens@clinica-demo.cl',          'nombre' => 'Pedro',     'apellido' => 'López',         'roles' => ['ROLE_TENS'],         'password' => 'tens1234'],
                ['email' => 'visualizador@clinica-demo.cl',  'nombre' => 'Lucía',     'apellido' => 'Rodríguez',     'roles' => ['ROLE_VISUALIZADOR'], 'password' => 'vis12345'],
            ],
        };

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
